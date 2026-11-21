<?php

namespace App\Services;

use App\Models\ManagedVpnAccount;
use App\Models\Server;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OutOfBoundsException;
use UnexpectedValueException;

class RouterOsVpnAccountService
{
    /**
     * @param  array{username: string, password: string, protocol: ?string, public_port: ?int, internal_port: ?int, remote_address?: string, local_address?: string, nat_comment?: ?string}  $input
     * @return array{id: string, username: string, remote_address: string, protocol: ?string, public_port: ?int, internal_port: ?int}
     */
    public function provision(Server $server, array $input): array
    {
        return Cache::lock('routeros:provision:'.$server->getKey(), 120)
            ->block(10, fn (): array => $this->provisionLocked($server, $input));
    }

    /**
     * @param  array{username: string, password: string, protocol: ?string, public_port: ?int, internal_port: ?int, remote_address?: string, local_address?: string, nat_comment?: ?string}  $input
     * @return array{id: string, username: string, remote_address: string, protocol: ?string, public_port: ?int, internal_port: ?int}
     */
    private function provisionLocked(Server $server, array $input): array
    {
        $router = RouterOsRestClient::forServer($server);
        $secrets = $router->collection('ppp/secret');

        if ($secrets->contains(fn (array $secret): bool => strcasecmp(
            (string) data_get($secret, 'name', ''),
            $input['username'],
        ) === 0)) {
            throw new InvalidArgumentException('An account with this username already exists on the selected CHR.');
        }

        $profileExists = $router->collection('ppp/profile')
            ->contains(fn (array $profile): bool => data_get($profile, 'name') === $server->ppp_profile);
        $pool = $router->collection('ip/pool')
            ->first(fn (array $configuredPool): bool => data_get($configuredPool, 'name') === $server->ip_pool);

        if (! $profileExists || ! is_array($pool)) {
            throw new InvalidArgumentException('The configured PPP profile or IP pool was not found on the selected CHR.');
        }

        $ranges = data_get($pool, 'ranges');

        if (! is_string($ranges) || trim($ranges) === '') {
            throw new InvalidArgumentException('The configured RouterOS IP pool has no usable ranges.');
        }

        $localAddress = $input['local_address'] ?? config('managed.vpn_local_address');

        if (! is_string($localAddress) || ! filter_var($localAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException('Configure a valid static IPv4 local address for the VPN.');
        }

        $reservedAddresses = $secrets
            ->flatMap(fn (array $secret): array => [
                data_get($secret, 'remote-address'),
                data_get($secret, 'local-address'),
            ])
            ->merge($router->collection('ppp/active')->flatMap(fn (array $session): array => [
                data_get($session, 'address'),
                data_get($session, 'local-address'),
            ]))
            ->merge($router->collection('ip/address')->map(
                fn (array $address): ?string => is_string(data_get($address, 'address'))
                    ? explode('/', data_get($address, 'address'))[0]
                    : null,
            ))
            ->push($localAddress)
            ->all();

        $allocator = new Ipv4PoolAllocator();
        $remoteAddress = $input['remote_address'] ?? $allocator->allocate($ranges, $reservedAddresses);

        if (! $allocator->isAvailable($ranges, $remoteAddress, $reservedAddresses)) {
            throw new InvalidArgumentException('The requested VPN address is outside the configured pool or already in use.');
        }
        $natRules = $input['protocol'] === null
            ? collect()
            : $router->collection('ip/firewall/nat');
        $publicPort = $input['protocol'] === null
            ? null
            : $this->allocatePublicPort($server, $natRules, $input['protocol'], $input['public_port']);

        $primaryOnly = (bool) config('managed.vpn_primary_only');
        $marker = 'sstp-console:'.$input['username'].':'.Str::uuid();
        $secretComment = $primaryOnly ? null : $marker;
        $natComment = $input['nat_comment'] ?? match ($input['internal_port']) {
            8291 => $input['username'].'-winbox',
            8728 => $input['username'].'-api',
            80 => $input['username'].'-webfig',
            default => $input['username'].'-manual',
        };

        if ($input['protocol'] !== null && $primaryOnly
            && (! is_string($natComment) || trim($natComment) === ''
                || preg_match('/[\x00-\x1F\x7F]/', $natComment) === 1)) {
            throw new InvalidArgumentException('A valid DST-NAT comment is required for the selected service.');
        }

        $natRuleComment = $primaryOnly ? $natComment : $marker;
        $started = false;
        $createdSecret = null;
        $createdNatRule = null;

        try {
            $started = true;
            $secretAttributes = [
                'name' => $input['username'],
                'password' => $input['password'],
                'service' => 'sstp',
                'profile' => $server->ppp_profile,
                'remote-address' => $remoteAddress,
                'local-address' => $localAddress,
                'disabled' => 'false',
            ];

            if ($secretComment !== null) {
                $secretAttributes['comment'] = $secretComment;
            }

            $router->create('ppp/secret', $secretAttributes);

            $createdSecret = $router->collection('ppp/secret')->first(
                fn (array $secret): bool => strcasecmp((string) data_get($secret, 'name', ''), $input['username']) === 0
                    && data_get($secret, 'service') === 'sstp'
                    && data_get($secret, 'remote-address') === $remoteAddress
                    && data_get($secret, 'local-address') === $localAddress
                    && ($secretComment === null || data_get($secret, 'comment') === $secretComment),
            );

            if (! is_array($createdSecret) || ! is_string($createdSecret['.id'] ?? null)) {
                throw new UnexpectedValueException('RouterOS did not return the created PPP secret for verification.');
            }

            if ($input['protocol'] !== null && $publicPort !== null && $input['internal_port'] !== null) {
                $router->create('ip/firewall/nat', [
                    'chain' => 'dstnat',
                    'action' => 'dst-nat',
                    'protocol' => $input['protocol'],
                    'dst-address' => $server->public_ip,
                    'dst-port' => (string) $publicPort,
                    'to-addresses' => $remoteAddress,
                    'to-ports' => (string) $input['internal_port'],
                    'comment' => $natRuleComment,
                    'disabled' => 'false',
                ]);

                $createdNatRule = $router->collection('ip/firewall/nat')->first(
                    fn (array $rule): bool => data_get($rule, 'comment') === $natRuleComment
                        && data_get($rule, 'protocol') === $input['protocol']
                        && data_get($rule, 'dst-address') === $server->public_ip
                        && data_get($rule, 'to-addresses') === $remoteAddress
                        && (string) data_get($rule, 'dst-port') === (string) $publicPort
                        && (string) data_get($rule, 'to-ports') === (string) $input['internal_port'],
                );

                if (! is_array($createdNatRule) || ! is_string($createdNatRule['.id'] ?? null)) {
                    throw new UnexpectedValueException('RouterOS did not return the created DST-NAT rule for verification.');
                }
            }
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            if ($started) {
                try {
                    if ($secretComment !== null) {
                        $this->removeByMarker($router, $marker);
                    } else {
                        $this->removeProvisionedAccount(
                            $router,
                            $input['username'],
                            $remoteAddress,
                            is_string($createdNatRule['.id'] ?? null) ? $createdNatRule['.id'] : null,
                            $input['protocol'],
                            $publicPort,
                            $server->public_ip,
                            $input['internal_port'],
                            is_string($natComment) ? $natComment : null,
                        );
                    }
                } catch (ConnectionException|RequestException|UnexpectedValueException $cleanupException) {
                    Log::critical('RouterOS provisioning rollback failed.', [
                        'server_id' => $server->getKey(),
                        'username' => $input['username'],
                        'exception' => $cleanupException::class,
                    ]);

                    throw new RouterOsProvisioningException(
                        'Provisioning may be incomplete on the router. Verify the account and NAT rule manually.',
                        previous: $cleanupException,
                    );
                }
            }

            throw $exception;
        }

        try {
            ManagedVpnAccount::query()->create([
                'server_id' => $server->getKey(),
                'routeros_id' => (string) $createdSecret['.id'],
                'username' => $input['username'],
                'remote_address' => $remoteAddress,
                'nat_rule_ids' => is_string($createdNatRule['.id'] ?? null)
                    ? [$createdNatRule['.id']]
                    : [],
            ]);
        } catch (QueryException $exception) {
            try {
                if ($secretComment !== null) {
                    $this->removeByMarker($router, $marker);
                } else {
                    $this->removeProvisionedAccount(
                        $router,
                        $input['username'],
                        $remoteAddress,
                        is_string($createdNatRule['.id'] ?? null) ? $createdNatRule['.id'] : null,
                        $input['protocol'],
                        $publicPort,
                        $server->public_ip,
                        $input['internal_port'],
                        is_string($natComment) ? $natComment : null,
                    );
                }
            } catch (ConnectionException|RequestException|UnexpectedValueException $cleanupException) {
                Log::critical('RouterOS provisioning rollback failed after local tracking could not be saved.', [
                    'server_id' => $server->getKey(),
                    'username' => $input['username'],
                    'exception' => $cleanupException::class,
                ]);

                throw new RouterOsProvisioningException(
                    'The VPN account was created but could not be safely tracked. Verify the account and NAT rule manually.',
                    previous: $cleanupException,
                );
            }

            throw new RouterOsProvisioningException(
                'The VPN account was rolled back because its ownership record could not be saved.',
                previous: $exception,
            );
        }

        return [
            'id' => (string) $createdSecret['.id'],
            'username' => $input['username'],
            'remote_address' => $remoteAddress,
            'protocol' => $input['protocol'],
            'public_port' => $publicPort,
            'internal_port' => $input['internal_port'],
        ];
    }

    public function updatePassword(Server $server, string $id, string $password): string
    {
        $router = RouterOsRestClient::forServer($server);
        $secret = $this->findSecret($router->collection('ppp/secret'), $id);

        if (! $this->isManagedSecret($server, $secret)) {
            throw new InvalidArgumentException('Only accounts created by this application can be changed.');
        }

        $router->update('ppp/secret', $id, ['password' => $password]);

        return (string) data_get($secret, 'name');
    }

    /**
     * @return array{account: array{id: string, username: string, remote_address: string}, script: string}
     */
    public function generateScript(Server $server, string $id, bool $includeSpeedtest = true): array
    {
        $router = RouterOsRestClient::forServer($server);
        $secret = $this->findSecret($router->collection('ppp/secret'), $id);

        if (data_get($secret, 'service') !== 'sstp') {
            throw new InvalidArgumentException('A client script can only be generated for an SSTP account.');
        }

        $username = data_get($secret, 'name');
        $password = data_get($secret, 'password');
        $remoteAddress = data_get($secret, 'remote-address');

        if (! is_string($username) || ! is_string($password) || $password === ''
            || ! is_string($remoteAddress)
            || ! is_string($secret['.id'] ?? null)) {
            throw new UnexpectedValueException('RouterOS did not return the credentials and address needed to generate the SSTP script.');
        }

        return [
            'account' => [
                'id' => $secret['.id'],
                'username' => $username,
                'remote_address' => $remoteAddress,
            ],
            'script' => (new RouterOsScriptGenerator())->generate($server, [
                'username' => $username,
                'password' => $password,
            ], includeSpeedtest: $includeSpeedtest),
        ];
    }

    /**
     * @return array{username: string, remote_address: string}
     */
    public function delete(Server $server, string $id): array
    {
        $router = RouterOsRestClient::forServer($server);
        $secret = $this->findSecret($router->collection('ppp/secret'), $id);

        if (! $this->isManagedSecret($server, $secret)) {
            throw new InvalidArgumentException('Only accounts created by this application can be deleted.');
        }

        $trackedAccount = ManagedVpnAccount::query()
            ->where('server_id', $server->getKey())
            ->where('routeros_id', $id)
            ->first();

        if (str_starts_with((string) data_get($secret, 'comment', ''), 'sstp-console:')) {
            $this->removeByMarker($router, (string) data_get($secret, 'comment'));
            $trackedAccount?->delete();
        } elseif ($trackedAccount instanceof ManagedVpnAccount) {
            $this->removeTrackedAccount($router, $secret, $trackedAccount);
        }

        return [
            'username' => (string) data_get($secret, 'name'),
            'remote_address' => (string) data_get($secret, 'remote-address'),
        ];
    }

    private function allocatePublicPort(Server $server, Collection $rules, string $protocol, ?int $requestedPort): int
    {
        $rangeStart = $server->port_range_start;
        $rangeEnd = $server->port_range_end;

        if (! filter_var($server->public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! is_int($rangeStart)
            || ! is_int($rangeEnd)
            || $rangeStart < 1
            || $rangeEnd > 65535
            || $rangeStart > $rangeEnd) {
            throw new InvalidArgumentException('The selected CHR has no valid public IP and public port range configured.');
        }

        if ($requestedPort !== null) {
            if (! $this->publicPortIsAvailable($server, $rules, $protocol, $requestedPort)) {
                throw new InvalidArgumentException('The requested public port is outside the configured range or is already in use.');
            }

            return $requestedPort;
        }

        for ($port = $rangeStart; $port <= $rangeEnd; $port++) {
            if ($this->publicPortIsAvailable($server, $rules, $protocol, $port)) {
                return $port;
            }
        }

        throw new OutOfBoundsException('No public ports remain in the configured range.');
    }

    public function publicPortIsAvailable(Server $server, Collection $rules, string $protocol, int $port): bool
    {
        $rangeStart = $server->port_range_start;
        $rangeEnd = $server->port_range_end;

        if (! filter_var($server->public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! is_int($rangeStart)
            || ! is_int($rangeEnd)
            || $rangeStart < 1
            || $rangeEnd > 65535
            || $rangeStart > $rangeEnd) {
            throw new InvalidArgumentException('The selected CHR has no valid public IP and public port range configured.');
        }

        return $port >= $rangeStart
            && $port <= $rangeEnd
            && ! $this->portIsInUse($rules, $protocol, $port, $server->public_ip);
    }

    private function portIsInUse(Collection $rules, string $protocol, int $port, string $publicIp): bool
    {
        return $rules->contains(function (array $rule) use ($protocol, $port, $publicIp): bool {
            if (data_get($rule, 'chain') !== 'dstnat'
                || filter_var(data_get($rule, 'disabled', 'false'), FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }

            $ruleProtocol = strtolower((string) data_get($rule, 'protocol', ''));

            if ($ruleProtocol !== '' && $ruleProtocol !== $protocol) {
                return false;
            }

            $destinationAddress = (string) data_get($rule, 'dst-address', '');

            if ($destinationAddress !== '') {
                $containsPublicIp = $this->addressRangeContains($destinationAddress, $publicIp);

                if ($containsPublicIp === false) {
                    return false;
                }
            }

            if (data_get($rule, 'dst-address-list') || data_get($rule, 'dst-address-type')) {
                return true;
            }

            $destinationPorts = trim((string) data_get($rule, 'dst-port', ''));

            if ($destinationPorts === '') {
                return true;
            }

            foreach (explode(',', $destinationPorts) as $portRange) {
                $portRange = trim($portRange);

                if (! preg_match('/^\d+(?:-\d+)?$/D', $portRange)) {
                    return true;
                }

                $parts = array_map('intval', explode('-', $portRange, 2));
                $start = $parts[0] ?? 0;
                $end = $parts[1] ?? $start;

                if ($start < 1 || $end > 65535 || $start > $end) {
                    return true;
                }

                if ($port >= $start && $port <= $end) {
                    return true;
                }
            }

            return false;
        });
    }

    private function addressRangeContains(string $range, string $address): ?bool
    {
        $parts = explode('/', $range, 2);
        $network = $parts[0];
        $prefix = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 32;

        if ((! filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            || (isset($parts[1]) && ! ctype_digit($parts[1]))
            || $prefix < 0
            || $prefix > 32) {
            return null;
        }

        $networkValue = ip2long($network);
        $addressValue = ip2long($address);
        $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix));

        return ($networkValue & $mask) === ($addressValue & $mask);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $secrets
     * @return array<string, mixed>
     */
    private function findSecret(Collection $secrets, string $id): array
    {
        $secret = $secrets->first(fn (array $entry): bool => ($entry['.id'] ?? null) === $id);

        if (! is_array($secret)) {
            throw new InvalidArgumentException('The VPN account was not found on the selected CHR.');
        }

        return $secret;
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

    private function removeTrackedAccount(
        RouterOsRestClient $router,
        array $secret,
        ManagedVpnAccount $trackedAccount,
    ): void {
        $natIds = $trackedAccount->nat_rule_ids ?? [];
        $natRules = $router->collection('ip/firewall/nat');

        foreach ($natIds as $natId) {
            if (! is_string($natId)) {
                throw new UnexpectedValueException('The local VPN ownership record contains an invalid NAT rule identifier.');
            }

            if ($natRules->contains(fn (array $rule): bool => ($rule['.id'] ?? null) === $natId)) {
                $router->delete('ip/firewall/nat', $natId);
            }
        }

        foreach ($natIds as $natId) {
            if ($router->collection('ip/firewall/nat')
                ->contains(fn (array $rule): bool => ($rule['.id'] ?? null) === $natId)) {
                throw new UnexpectedValueException('RouterOS did not remove the tracked DST-NAT rule.');
            }
        }

        $secretId = (string) $secret['.id'];
        $router->delete('ppp/secret', $secretId);

        if ($router->collection('ppp/secret')
            ->contains(fn (array $entry): bool => ($entry['.id'] ?? null) === $secretId)) {
            throw new UnexpectedValueException('RouterOS did not remove the tracked PPP secret.');
        }

        $trackedAccount->delete();
    }

    private function removeProvisionedAccount(
        RouterOsRestClient $router,
        string $username,
        string $remoteAddress,
        ?string $natId,
        ?string $protocol,
        ?int $publicPort,
        ?string $publicIp,
        ?int $internalPort,
        ?string $natComment,
    ): void {
        if ($natId !== null) {
            if ($router->collection('ip/firewall/nat')
                ->contains(fn (array $rule): bool => ($rule['.id'] ?? null) === $natId)) {
                $router->delete('ip/firewall/nat', $natId);
            }

            if ($router->collection('ip/firewall/nat')
                ->contains(fn (array $rule): bool => ($rule['.id'] ?? null) === $natId)) {
                throw new UnexpectedValueException('RouterOS did not remove the DST-NAT rule during rollback.');
            }
        } elseif ($protocol !== null && $publicPort !== null && $internalPort !== null && $natComment !== null) {
            $matchesCreatedRule = fn (array $rule): bool => data_get($rule, 'chain') === 'dstnat'
                    && data_get($rule, 'action') === 'dst-nat'
                    && data_get($rule, 'comment') === $natComment
                    && data_get($rule, 'protocol') === $protocol
                    && data_get($rule, 'dst-address') === $publicIp
                    && (string) data_get($rule, 'dst-port') === (string) $publicPort
                    && data_get($rule, 'to-addresses') === $remoteAddress
                    && (string) data_get($rule, 'to-ports') === (string) $internalPort;
            $rules = $router->collection('ip/firewall/nat')->filter($matchesCreatedRule);

            foreach ($rules as $rule) {
                if (! is_string($rule['.id'] ?? null)) {
                    throw new UnexpectedValueException('RouterOS returned an unidentifiable DST-NAT rule during rollback.');
                }

                $router->delete('ip/firewall/nat', $rule['.id']);
            }

            if ($router->collection('ip/firewall/nat')->contains($matchesCreatedRule)) {
                throw new UnexpectedValueException('RouterOS did not remove the DST-NAT rule during rollback.');
            }
        }

        $secret = $router->collection('ppp/secret')->first(
            fn (array $entry): bool => strcasecmp((string) data_get($entry, 'name', ''), $username) === 0
                && data_get($entry, 'service') === 'sstp'
                && data_get($entry, 'remote-address') === $remoteAddress,
        );

        if (is_array($secret)) {
            if (! is_string($secret['.id'] ?? null)) {
                throw new UnexpectedValueException('RouterOS returned an unidentifiable PPP secret during rollback.');
            }

            $router->delete('ppp/secret', $secret['.id']);
        }

        if ($router->collection('ppp/secret')->contains(
            fn (array $entry): bool => strcasecmp((string) data_get($entry, 'name', ''), $username) === 0
                && data_get($entry, 'service') === 'sstp'
                && data_get($entry, 'remote-address') === $remoteAddress,
        )) {
            throw new UnexpectedValueException('RouterOS did not remove the untracked PPP secret during rollback.');
        }
    }

    private function removeByMarker(RouterOsRestClient $router, string $marker): void
    {
        $rules = $router->collection('ip/firewall/nat')
            ->filter(fn (array $rule): bool => data_get($rule, 'comment') === $marker);

        foreach ($rules as $rule) {
            $id = $rule['.id'] ?? null;

            if (! is_string($id)) {
                throw new UnexpectedValueException('RouterOS returned a NAT rule without an item identifier.');
            }

            $router->delete('ip/firewall/nat', $id);
        }

        $secrets = $router->collection('ppp/secret')
            ->filter(fn (array $secret): bool => data_get($secret, 'comment') === $marker);

        foreach ($secrets as $secret) {
            $id = $secret['.id'] ?? null;

            if (! is_string($id)) {
                throw new UnexpectedValueException('RouterOS returned a PPP secret without an item identifier.');
            }

            $router->delete('ppp/secret', $id);
        }

        if ($router->collection('ip/firewall/nat')
            ->contains(fn (array $rule): bool => data_get($rule, 'comment') === $marker)
            || $router->collection('ppp/secret')
                ->contains(fn (array $secret): bool => data_get($secret, 'comment') === $marker)) {
            throw new UnexpectedValueException('RouterOS did not remove all resources marked for this account.');
        }
    }
}
