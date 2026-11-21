<?php

namespace App\Services;

use App\Models\ManagedVpnAccount;
use App\Models\Server;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OutOfBoundsException;
use UnexpectedValueException;

class RouterOsClusterVpnAccountService
{
    public function __construct(private readonly RouterOsVpnAccountService $accounts) {}

    /**
     * @param  Collection<int, Server>  $servers
     * @param  array{username: string, password: string, protocol: ?string, public_port: ?int, internal_port: ?int}  $input
     * @return array{id: string, username: string, remote_address: string, protocol: ?string, public_port: ?int, internal_port: ?int}
     */
    public function provision(Collection $servers, array $input): array
    {
        $servers = $this->orderedServers($servers);
        $lockName = 'routeros:provision:cluster:'.sha1(implode(',', $servers->modelKeys()));

        return Cache::lock($lockName, 120)
            ->block(10, fn (): array => $this->provisionLocked($servers, $input));
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @param  array{username: string, password: string, protocol: ?string, public_port: ?int, internal_port: ?int}  $input
     * @return array{id: string, username: string, remote_address: string, protocol: ?string, public_port: ?int, internal_port: ?int}
     */
    private function provisionLocked(Collection $servers, array $input): array
    {
        $nodes = [];
        $poolRanges = null;
        $localAddresses = [];
        $reservedAddresses = [];

        foreach ($servers as $server) {
            $router = RouterOsRestClient::forServer($server);
            $secrets = $router->collection('ppp/secret');

            if ($secrets->contains(fn (array $secret): bool => strcasecmp(
                (string) data_get($secret, 'name', ''),
                $input['username'],
            ) === 0)) {
                throw new InvalidArgumentException('An account with this username already exists on a cluster node.');
            }

            $profile = $router->collection('ppp/profile')
                ->first(fn (array $entry): bool => data_get($entry, 'name') === $server->ppp_profile);
            $pool = $router->collection('ip/pool')
                ->first(fn (array $entry): bool => data_get($entry, 'name') === $server->ip_pool);

            if (! is_array($profile) || ! is_array($pool)) {
                throw new InvalidArgumentException('The configured PPP profile or IP pool was not found on every cluster node.');
            }

            $ranges = data_get($pool, 'ranges');

            if (! is_string($ranges) || trim($ranges) === '') {
                throw new InvalidArgumentException('The configured RouterOS IP pool has no usable ranges.');
            }

            $normalizedRanges = implode(',', array_map('trim', explode(',', $ranges)));
            $poolRanges ??= $normalizedRanges;

            if ($poolRanges !== $normalizedRanges) {
                throw new InvalidArgumentException('All cluster nodes must use the same VPN IP pool ranges.');
            }

            $localAddresses[] = (string) data_get($profile, 'local-address', '');
            $natRules = $input['protocol'] === null
                ? collect()
                : $router->collection('ip/firewall/nat');
            $activeSessions = $router->collection('ppp/active');
            $addresses = $router->collection('ip/address');

            $reservedAddresses = array_merge(
                $reservedAddresses,
                $secrets->flatMap(fn (array $secret): array => [
                    data_get($secret, 'remote-address'),
                    data_get($secret, 'local-address'),
                ])->all(),
                $activeSessions->flatMap(fn (array $session): array => [
                    data_get($session, 'address'),
                    data_get($session, 'local-address'),
                ])->all(),
                $addresses->map(fn (array $address): ?string => is_string(data_get($address, 'address'))
                    ? explode('/', data_get($address, 'address'))[0]
                    : null)->all(),
            );

            $nodes[] = [
                'server' => $server,
                'nat_rules' => $natRules,
            ];
        }

        if (count(array_unique($localAddresses)) > 1) {
            throw new InvalidArgumentException('All cluster nodes must use the same PPP local address.');
        }

        $remoteAddress = (new Ipv4PoolAllocator())->allocate((string) $poolRanges, $reservedAddresses);

        foreach ($nodes as $node) {
            if (! (new Ipv4PoolAllocator())->isAvailable(
                (string) $poolRanges,
                $remoteAddress,
                $reservedAddresses,
            )) {
                throw new InvalidArgumentException('A common VPN address is not available on every cluster node.');
            }
        }

        $publicPort = $this->allocateCommonPort($nodes, $input['protocol'], $input['public_port']);
        $created = [];

        try {
            foreach ($nodes as $node) {
                /** @var Server $server */
                $server = $node['server'];
                $result = $this->accounts->provision($server, [
                    ...$input,
                    'public_port' => $publicPort,
                    'remote_address' => $remoteAddress,
                ]);
                $created[] = ['server' => $server, 'account' => $result];
            }
        } catch (ConnectionException|RequestException|UnexpectedValueException|RouterOsProvisioningException $exception) {
            $this->rollbackProvisioning($created, $input['username']);
            throw $exception;
        } catch (InvalidArgumentException|OutOfBoundsException $exception) {
            $this->rollbackProvisioning($created, $input['username']);
            throw $exception;
        }

        return $created[0]['account'];
    }

    /**
     * @param  Collection<int, Server>  $servers
     */
    public function updatePassword(Collection $servers, string $primaryId, string $password): string
    {
        $servers = $this->orderedServers($servers);

        if ($servers->count() === 1) {
            return $this->accounts->updatePassword($servers->first(), $primaryId, $password);
        }

        $secretsByServer = $this->managedSecrets($servers, $primaryId);
        $username = (string) data_get($secretsByServer[0]['secret'], 'name');
        $oldPassword = data_get($secretsByServer[0]['secret'], 'password');

        if (! is_string($oldPassword) || $oldPassword === '') {
            throw new UnexpectedValueException('RouterOS did not return the password needed to keep cluster replicas consistent.');
        }

        foreach ($secretsByServer as $entry) {
            if (data_get($entry['secret'], 'password') !== $oldPassword
                || data_get($entry['secret'], 'remote-address') !== data_get($secretsByServer[0]['secret'], 'remote-address')) {
                throw new UnexpectedValueException('The managed account is not synchronized across all cluster nodes.');
            }
        }

        $updated = [];

        try {
            foreach ($secretsByServer as $entry) {
                $this->accounts->updatePassword($entry['server'], (string) $entry['secret']['.id'], $password);
                $updated[] = $entry;
            }
        } catch (ConnectionException|RequestException|InvalidArgumentException $exception) {
            foreach (array_reverse($updated) as $entry) {
                try {
                    $this->accounts->updatePassword(
                        $entry['server'],
                        (string) $entry['secret']['.id'],
                        $oldPassword,
                    );
                } catch (ConnectionException|RequestException|InvalidArgumentException $rollbackException) {
                    Log::critical('RouterOS cluster password rollback failed.', [
                        'server_id' => $entry['server']->getKey(),
                        'username' => $username,
                        'exception' => $rollbackException::class,
                    ]);

                    throw new RouterOsProvisioningException(
                        'The password may differ between cluster nodes. Verify the account on every router before retrying.',
                        previous: $rollbackException,
                    );
                }
            }

            throw $exception;
        }

        return $username;
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array{account: array{id: string, username: string, remote_address: string}, script: string}
     */
    public function generateScript(Collection $servers, string $primaryId, bool $includeSpeedtest = true): array
    {
        $servers = $this->orderedServers($servers);

        if ($servers->count() === 1) {
            return $this->accounts->generateScript($servers->first(), $primaryId, $includeSpeedtest);
        }

        $secretsByServer = $this->managedSecrets($servers, $primaryId);
        $primarySecret = $secretsByServer[0]['secret'];
        $username = data_get($primarySecret, 'name');
        $password = data_get($primarySecret, 'password');
        $remoteAddress = data_get($primarySecret, 'remote-address');

        if (! is_string($username) || ! is_string($password) || $password === ''
            || ! is_string($remoteAddress) || ! is_string($primarySecret['.id'] ?? null)) {
            throw new UnexpectedValueException('RouterOS did not return the credentials and address needed to generate the SSTP script.');
        }

        foreach ($secretsByServer as $entry) {
            if (data_get($entry['secret'], 'password') !== $password
                || data_get($entry['secret'], 'remote-address') !== $remoteAddress) {
                throw new UnexpectedValueException('The managed account is not synchronized across all cluster nodes.');
            }
        }

        $endpoints = $servers->map(function (Server $server): string {
            $endpoint = $server->host;

            if (! is_string($endpoint) || trim($endpoint) === '') {
                throw new InvalidArgumentException('Configure a RouterOS hostname or certificate-matching IP for every cluster node before generating a script.');
            }

            return trim($endpoint, '[]');
        })->all();

        return [
            'account' => [
                'id' => $primarySecret['.id'],
                'username' => $username,
                'remote_address' => $remoteAddress,
            ],
            'script' => (new RouterOsScriptGenerator())->generate(
                $servers->first(),
                ['username' => $username, 'password' => $password],
                $endpoints,
                $includeSpeedtest,
            ),
        ];
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array{accounts: int, replicas_created: int}
     */
    public function synchronizeManagedAccounts(Collection $servers): array
    {
        if (config('managed.vpn_primary_only')) {
            throw new InvalidArgumentException('Cluster synchronization is disabled while VPN management is in single-CHR mode.');
        }

        $servers = $this->orderedServers($servers);
        $primary = $servers->first();
        $sourceRouter = RouterOsRestClient::forServer($primary);
        $sourceProfile = $sourceRouter->collection('ppp/profile')
            ->first(fn (array $entry): bool => data_get($entry, 'name') === $primary->ppp_profile);
        $sourcePool = $sourceRouter->collection('ip/pool')
            ->first(fn (array $entry): bool => data_get($entry, 'name') === $primary->ip_pool);

        if (! is_array($sourceProfile) || ! is_array($sourcePool)) {
            throw new InvalidArgumentException('The primary node PPP profile or IP pool was not found.');
        }

        $sourceRanges = data_get($sourcePool, 'ranges');

        if (! is_string($sourceRanges) || trim($sourceRanges) === '') {
            throw new InvalidArgumentException('The primary node IP pool has no usable ranges.');
        }

        $sourceSecrets = $sourceRouter->collection('ppp/secret')
            ->filter(fn (array $secret): bool => data_get($secret, 'service') === 'sstp'
                && str_starts_with((string) data_get($secret, 'comment', ''), 'sstp-console:'))
            ->values();
        $sourceNatRules = $sourceRouter->collection('ip/firewall/nat');
        $accounts = [];

        foreach ($sourceSecrets as $secret) {
            $username = data_get($secret, 'name');
            $password = data_get($secret, 'password');
            $remoteAddress = data_get($secret, 'remote-address');
            $marker = data_get($secret, 'comment');

            if (! is_string($username) || ! is_string($password) || $password === ''
                || ! is_string($remoteAddress) || ! is_string($marker)) {
                throw new UnexpectedValueException('RouterOS did not return all managed account values needed for safe synchronization.');
            }

            $accounts[] = [
                'secret' => $secret,
                'nat_rules' => $sourceNatRules
                    ->filter(fn (array $rule): bool => data_get($rule, 'comment') === $marker)
                    ->values()
                    ->all(),
            ];
        }

        $targets = [];

        foreach ($servers->skip(1) as $server) {
            $router = RouterOsRestClient::forServer($server);
            $secrets = $router->collection('ppp/secret');
            $profile = $router->collection('ppp/profile')
                ->first(fn (array $entry): bool => data_get($entry, 'name') === $server->ppp_profile);
            $pool = $router->collection('ip/pool')
                ->first(fn (array $entry): bool => data_get($entry, 'name') === $server->ip_pool);
            $natRules = $router->collection('ip/firewall/nat');

            if (! is_array($profile) || ! is_array($pool)
                || data_get($profile, 'local-address', '') !== data_get($sourceProfile, 'local-address', '')) {
                throw new InvalidArgumentException('Cluster PPP profiles must be present and have the same local address before synchronization.');
            }

            $ranges = data_get($pool, 'ranges');

            if (! is_string($ranges) || trim($ranges) === ''
                || implode(',', array_map('trim', explode(',', $ranges)))
                    !== implode(',', array_map('trim', explode(',', $sourceRanges)))) {
                throw new InvalidArgumentException('All cluster nodes must use the same usable VPN IP pool ranges.');
            }

            $reserved = $secrets->flatMap(fn (array $secret): array => [
                data_get($secret, 'remote-address'),
                data_get($secret, 'local-address'),
            ])->merge(
                $router->collection('ppp/active')->flatMap(fn (array $session): array => [
                    data_get($session, 'address'),
                    data_get($session, 'local-address'),
                ]),
            )->merge(
                $router->collection('ip/address')->map(fn (array $address): ?string => is_string(data_get($address, 'address'))
                    ? explode('/', data_get($address, 'address'))[0]
                    : null),
            )->all();
            $plannedAccounts = [];

            foreach ($accounts as $account) {
                $sourceSecret = $account['secret'];
                $username = (string) data_get($sourceSecret, 'name');
                $existing = $secrets->first(fn (array $secret): bool => strcasecmp(
                    (string) data_get($secret, 'name', ''),
                    $username,
                ) === 0);

                if (is_array($existing)) {
                    if (! $this->isManagedSecret($server, $existing)
                        || data_get($existing, 'remote-address') !== data_get($sourceSecret, 'remote-address')
                        || data_get($existing, 'password') !== data_get($sourceSecret, 'password')
                        || data_get($existing, 'profile') !== data_get($sourceSecret, 'profile')) {
                        throw new InvalidArgumentException("Account {$username} conflicts with an existing account on {$server->name}.");
                    }

                    $existingMarker = data_get($existing, 'comment');

                    foreach ($account['nat_rules'] as $sourceNatRule) {
                        $port = (string) data_get($sourceNatRule, 'dst-port', '');
                        $ruleExists = $natRules->contains(fn (array $existingRule): bool => data_get($existingRule, 'comment') === $existingMarker
                            && (string) data_get($existingRule, 'dst-port', '') === $port
                            && data_get($existingRule, 'to-addresses') === data_get($sourceSecret, 'remote-address')
                            && data_get($existingRule, 'dst-address') === $server->public_ip);

                        if (! $ruleExists) {
                            throw new InvalidArgumentException("NAT rules for existing account {$username} are incomplete on {$server->name}; review them before retrying.");
                        }
                    }

                    continue;
                }

                $remoteAddress = (string) data_get($sourceSecret, 'remote-address');

                if (! (new Ipv4PoolAllocator())->isAvailable($ranges, $remoteAddress, $reserved)) {
                    throw new InvalidArgumentException("VPN address {$remoteAddress} is unavailable on {$server->name}.");
                }

                $reserved[] = $remoteAddress;
                $plannedNatRules = [];

                foreach ($account['nat_rules'] as $natRule) {
                    $protocol = data_get($natRule, 'protocol');
                    $port = filter_var(data_get($natRule, 'dst-port'), FILTER_VALIDATE_INT);

                    if (! is_string($protocol) || ! is_int($port)
                        || ! filter_var($server->public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new InvalidArgumentException("A managed NAT rule for {$username} cannot be synchronized to {$server->name}.");
                    }

                    $alreadyExists = $natRules->contains(fn (array $existingRule): bool => data_get($existingRule, 'comment') === data_get($sourceSecret, 'comment')
                        && (string) data_get($existingRule, 'dst-port', '') === (string) $port
                        && data_get($existingRule, 'to-addresses') === $remoteAddress);

                    if (! $alreadyExists && ! $this->accounts->publicPortIsAvailable($server, $natRules, $protocol, $port)) {
                        throw new InvalidArgumentException("NAT port {$port} for {$username} is already in use on {$server->name}.");
                    }

                    if (! $alreadyExists) {
                        $plannedNatRules[] = $this->replicaNatAttributes($natRule, $server->public_ip);
                    }
                }

                $plannedAccounts[] = [
                    'secret' => $sourceSecret,
                    'nat_rules' => $plannedNatRules,
                ];
            }

            $targets[] = [
                'server' => $server,
                'accounts' => $plannedAccounts,
            ];
        }

        $created = [];

        try {
            foreach ($targets as $target) {
                $server = $target['server'];
                $router = RouterOsRestClient::forServer($server);

                foreach ($target['accounts'] as $account) {
                    $sourceSecret = $account['secret'];
                    $router->create('ppp/secret', $this->replicaSecretAttributes($sourceSecret));
                    $createdSecret = $router->collection('ppp/secret')->first(
                        fn (array $secret): bool => data_get($secret, 'comment') === data_get($sourceSecret, 'comment')
                            && data_get($secret, 'name') === data_get($sourceSecret, 'name'),
                    );

                    if (! is_array($createdSecret) || ! is_string($createdSecret['.id'] ?? null)) {
                        throw new UnexpectedValueException('RouterOS did not confirm a synchronized PPP account.');
                    }

                    $created[] = ['server' => $server, 'id' => $createdSecret['.id']];

                    foreach ($account['nat_rules'] as $natRule) {
                        $router->create('ip/firewall/nat', $natRule);
                    }

                    $marker = (string) data_get($sourceSecret, 'comment');
                    $expectedNatCount = count($account['nat_rules']);
                    $actualNatCount = $router->collection('ip/firewall/nat')
                        ->filter(fn (array $rule): bool => data_get($rule, 'comment') === $marker)
                        ->count();

                    if ($actualNatCount < $expectedNatCount) {
                        throw new UnexpectedValueException('RouterOS did not confirm every synchronized NAT rule.');
                    }
                }
            }
        } catch (ConnectionException|RequestException|UnexpectedValueException|RouterOsProvisioningException $exception) {
            $this->rollbackSynchronizedAccounts($created);
            throw $exception;
        }

        return [
            'accounts' => count($accounts),
            'replicas_created' => count($created),
        ];
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array{username: string, remote_address: string}
     */
    public function delete(Collection $servers, string $primaryId): array
    {
        $servers = $this->orderedServers($servers);

        if ($servers->count() === 1) {
            return $this->accounts->delete($servers->first(), $primaryId);
        }

        $accounts = $this->managedSecrets($servers, $primaryId);
        $username = (string) data_get($accounts[0]['secret'], 'name');
        $remoteAddress = (string) data_get($accounts[0]['secret'], 'remote-address');
        $snapshots = [];

        foreach ($accounts as $entry) {
            $server = $entry['server'];
            $secret = $entry['secret'];
            $marker = (string) data_get($secret, 'comment');
            $natRules = RouterOsRestClient::forServer($server)->collection('ip/firewall/nat')
                ->filter(fn (array $rule): bool => data_get($rule, 'comment') === $marker)
                ->values()
                ->all();
            $snapshots[] = compact('server', 'secret', 'natRules');
        }

        try {
            foreach ($accounts as $entry) {
                $this->accounts->delete($entry['server'], (string) $entry['secret']['.id']);
            }
        } catch (ConnectionException|RequestException|UnexpectedValueException|InvalidArgumentException $exception) {
            foreach ($snapshots as $snapshot) {
                try {
                    $this->restoreSnapshot($snapshot);
                } catch (ConnectionException|RequestException|UnexpectedValueException $rollbackException) {
                    Log::critical('RouterOS cluster deletion rollback failed.', [
                        'server_id' => $snapshot['server']->getKey(),
                        'username' => $username,
                        'exception' => $rollbackException::class,
                    ]);

                    throw new RouterOsProvisioningException(
                        'The account may be missing on one or more cluster nodes. Verify every router before retrying.',
                        previous: $rollbackException,
                    );
                }
            }

            throw $exception;
        }

        return ['username' => $username, 'remote_address' => $remoteAddress];
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array<int, array{server: Server, secret: array<string, mixed>}>
     */
    private function managedSecrets(Collection $servers, string $primaryId): array
    {
        $entries = [];
        $username = null;

        foreach ($servers as $index => $server) {
            $secrets = RouterOsRestClient::forServer($server)->collection('ppp/secret');
            $secret = $index === 0
                ? $secrets->first(fn (array $entry): bool => ($entry['.id'] ?? null) === $primaryId)
                : $secrets->first(fn (array $entry): bool => strcasecmp(
                    (string) data_get($entry, 'name', ''),
                    (string) $username,
                ) === 0);

            if ($index === 0 && (! is_array($secret) || ! $this->isManagedSecret($server, $secret))) {
                throw new InvalidArgumentException('The managed VPN account was not found on the primary node.');
            }

            if (! is_array($secret) || ! $this->isManagedSecret($server, $secret)) {
                throw new UnexpectedValueException('The managed VPN account is missing from one or more backup nodes. Synchronize managed accounts first.');
            }

            if (! is_string(data_get($secret, 'name')) || ! is_string($secret['.id'] ?? null)) {
                throw new InvalidArgumentException('The managed VPN account is missing from one or more cluster nodes.');
            }

            $username ??= data_get($secret, 'name');
            $entries[] = ['server' => $server, 'secret' => $secret];
        }

        $primaryName = (string) data_get($entries[0]['secret'], 'name');
        $primaryAddress = data_get($entries[0]['secret'], 'remote-address');

        foreach ($entries as $entry) {
            if (strcasecmp((string) data_get($entry['secret'], 'name', ''), $primaryName) !== 0
                || data_get($entry['secret'], 'remote-address') !== $primaryAddress) {
                throw new UnexpectedValueException('The managed account identity or VPN address differs between cluster nodes. Synchronize the cluster before retrying.');
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, array{server: Server, nat_rules: Collection<int, array<string, mixed>>}>  $nodes
     */
    private function allocateCommonPort(array $nodes, ?string $protocol, ?int $requestedPort): ?int
    {
        if ($protocol === null) {
            return null;
        }

        $starts = array_map(fn (array $node): int => (int) $node['server']->port_range_start, $nodes);
        $ends = array_map(fn (array $node): int => (int) $node['server']->port_range_end, $nodes);
        $start = max($starts);
        $end = min($ends);

        if ($start < 1 || $end > 65535 || $start > $end) {
            throw new InvalidArgumentException('All cluster nodes must have an overlapping public NAT port range.');
        }

        if ($requestedPort !== null) {
            if ($requestedPort < $start || $requestedPort > $end
                || ! $this->portIsFreeOnEveryNode($nodes, $protocol, $requestedPort)) {
                throw new InvalidArgumentException('The requested public port is outside the cluster range or already used on a node.');
            }

            return $requestedPort;
        }

        for ($port = $start; $port <= $end; $port++) {
            if ($this->portIsFreeOnEveryNode($nodes, $protocol, $port)) {
                return $port;
            }
        }

        throw new OutOfBoundsException('No public port is free on every cluster node.');
    }

    /**
     * @param  array<int, array{server: Server, nat_rules: Collection<int, array<string, mixed>>}>  $nodes
     */
    private function portIsFreeOnEveryNode(array $nodes, string $protocol, int $port): bool
    {
        foreach ($nodes as $node) {
            if (! $this->accounts->publicPortIsAvailable(
                $node['server'],
                $node['nat_rules'],
                $protocol,
                $port,
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{server: Server, account: array{id: string, username: string, remote_address: string, protocol: ?string, public_port: ?int, internal_port: ?int}}>  $created
     */
    private function rollbackProvisioning(array $created, string $username): void
    {
        foreach (array_reverse($created) as $entry) {
            try {
                $this->accounts->delete($entry['server'], $entry['account']['id']);
            } catch (ConnectionException|RequestException|UnexpectedValueException|InvalidArgumentException $exception) {
                Log::critical('RouterOS cluster provisioning rollback failed.', [
                    'server_id' => $entry['server']->getKey(),
                    'username' => $username,
                    'exception' => $exception::class,
                ]);

                throw new RouterOsProvisioningException(
                    'Provisioning may be incomplete on one or more cluster nodes. Verify the account and NAT rule manually.',
                    previous: $exception,
                );
            }
        }
    }

    /**
     * @param  array{server: Server, secret: array<string, mixed>, natRules: array<int, array<string, mixed>>}  $snapshot
     */
    private function restoreSnapshot(array $snapshot): void
    {
        $router = RouterOsRestClient::forServer($snapshot['server']);
        $marker = (string) data_get($snapshot['secret'], 'comment');
        $secrets = $router->collection('ppp/secret');
        $existingSecret = $secrets->first(fn (array $secret): bool => data_get($secret, 'comment') === $marker);

        if (! is_array($existingSecret)) {
            $router->create('ppp/secret', array_filter(
                array_intersect_key($snapshot['secret'], array_flip([
                    'name', 'password', 'service', 'profile', 'remote-address', 'local-address', 'comment', 'disabled',
                ])),
                fn (mixed $value): bool => $value !== null,
            ));
        }

        $natRules = $router->collection('ip/firewall/nat');

        foreach ($snapshot['natRules'] as $natRule) {
            $exists = $natRules->contains(fn (array $existing): bool => data_get($existing, 'comment') === $marker
                && (string) data_get($existing, 'dst-port', '') === (string) data_get($natRule, 'dst-port', '')
                && data_get($existing, 'to-addresses') === data_get($natRule, 'to-addresses'));

            if (! $exists) {
                $router->create('ip/firewall/nat', array_filter(
                    array_intersect_key($natRule, array_flip([
                        'chain', 'action', 'protocol', 'dst-address', 'dst-port', 'to-addresses', 'to-ports', 'comment', 'disabled',
                    ])),
                    fn (mixed $value): bool => $value !== null,
                ));
            }
        }

        if (! $router->collection('ppp/secret')
            ->contains(fn (array $secret): bool => data_get($secret, 'comment') === $marker)) {
            throw new UnexpectedValueException('RouterOS did not restore the VPN account during cluster rollback.');
        }

        $restoredNatRules = $router->collection('ip/firewall/nat')
            ->filter(fn (array $rule): bool => data_get($rule, 'comment') === $marker);

        if ($restoredNatRules->count() !== count($snapshot['natRules'])) {
            throw new UnexpectedValueException('RouterOS did not restore every NAT rule during cluster rollback.');
        }
    }

    /**
     * @param  array<string, mixed>  $secret
     * @return array<string, mixed>
     */
    private function replicaSecretAttributes(array $secret): array
    {
        return array_filter(
            array_intersect_key($secret, array_flip([
                'name', 'password', 'service', 'profile', 'remote-address', 'local-address', 'comment', 'disabled',
            ])),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function replicaNatAttributes(array $rule, string $publicIp): array
    {
        $attributes = array_intersect_key($rule, array_flip([
            'chain', 'action', 'protocol', 'dst-port', 'to-addresses', 'to-ports', 'comment', 'disabled',
        ]));
        $attributes['dst-address'] = $publicIp;

        return $attributes;
    }

    /**
     * @param  array<int, array{server: Server, id: string}>  $created
     */
    private function rollbackSynchronizedAccounts(array $created): void
    {
        foreach (array_reverse($created) as $entry) {
            try {
                $this->accounts->delete($entry['server'], $entry['id']);
            } catch (ConnectionException|RequestException|UnexpectedValueException|InvalidArgumentException $exception) {
                Log::critical('RouterOS cluster synchronization rollback failed.', [
                    'server_id' => $entry['server']->getKey(),
                    'exception' => $exception::class,
                ]);

                throw new RouterOsProvisioningException(
                    'Existing managed accounts may have been only partially synchronized. Verify every cluster node before retrying.',
                    previous: $exception,
                );
            }
        }
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return Collection<int, Server>
     */
    private function orderedServers(Collection $servers): Collection
    {
        $servers = $servers->filter(fn (mixed $server): bool => $server instanceof Server)
            ->sortBy(fn (Server $server): array => [$server->priority, $server->getKey()])
            ->values();

        if ($servers->isEmpty()) {
            throw new InvalidArgumentException('No active RouterOS cluster nodes are configured.');
        }

        return $servers;
    }

    /**
     * @param  array<string, mixed>  $secret
     */
    private function isManagedSecret(Server $server, array $secret): bool
    {
        if (data_get($secret, 'service') !== 'sstp') {
            return false;
        }

        return str_starts_with((string) data_get($secret, 'comment', ''), 'sstp-console:')
            || (is_string($secret['.id'] ?? null) && ManagedVpnAccount::query()
                ->where('server_id', $server->getKey())
                ->where('routeros_id', $secret['.id'])
                ->exists());
    }
}
