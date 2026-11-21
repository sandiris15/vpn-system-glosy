<?php

namespace SimpleVpn;

use InvalidArgumentException;
use RuntimeException;

final class VpnProvisioner
{
    public function __construct(
        private readonly RouterOsApi $router,
        private readonly Config $config,
        private readonly string $lockPath,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{username: string, remote_address: string, nat_rule: ?array<string, string>, script: ?string}
     */
    public function create(array $input): array
    {
        $input = $this->validate($input);
        $lock = $this->acquireLock();

        try {
            return $this->createLocked($input);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $protocol = trim((string) ($input['protocol'] ?? ''));
        $comment = trim(str_replace(["\r", "\n"], ' ', (string) ($input['comment'] ?? '')));

        if (! preg_match('/^[A-Za-z0-9._-]{1,64}$/', $username)) {
            throw new InvalidArgumentException('Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.');
        }

        if (strlen($password) < 8 || strlen($password) > 255) {
            throw new InvalidArgumentException('Password VPN harus terdiri dari 8 sampai 255 karakter.');
        }

        if ($protocol !== '' && ! in_array($protocol, ['tcp', 'udp'], true)) {
            throw new InvalidArgumentException('Pilih protokol TCP atau UDP.');
        }

        if ($comment !== '' && strlen($comment) > 160) {
            throw new InvalidArgumentException('Comment maksimal 160 karakter.');
        }

        $internalService = (string) ($input['internal_port'] ?? '');
        $customInternalPort = $input['custom_internal_port'] ?? '';

        if ($protocol === '') {
            if ($internalService !== '' || $customInternalPort !== '' || $comment !== '') {
                throw new InvalidArgumentException('Pilih protokol jika ingin membuat DST-NAT.');
            }

            return [
                'username' => $username,
                'password' => $password,
                'protocol' => null,
                'internal_port' => null,
                'comment' => '',
            ];
        }

        if (! in_array($internalService, ['8291', '8728', '80', 'custom'], true)) {
            throw new InvalidArgumentException('Pilih layanan internal atau port manual.');
        }

        if ($internalService === 'custom') {
            if (! ctype_digit((string) $customInternalPort)
                || (int) $customInternalPort < 1
                || (int) $customInternalPort > 65535) {
                throw new InvalidArgumentException('Port internal manual harus antara 1 dan 65535.');
            }

            if ($comment === '') {
                throw new InvalidArgumentException('Comment manual DST-NAT harus diisi.');
            }

            $internalPort = (int) $customInternalPort;
        } else {
            if ($customInternalPort !== '' || $comment !== '') {
                throw new InvalidArgumentException('Port atau comment manual hanya tersedia pada pilihan manual.');
            }

            $internalPort = (int) $internalService;
            $comment = $username.'-'.[
                '8291' => 'winbox',
                '8728' => 'mikrotik-api',
                '80' => 'webfig',
            ][$internalService];
        }

        return [
            'username' => $username,
            'password' => $password,
            'protocol' => $protocol,
            'internal_port' => $internalPort,
            'comment' => $comment !== '' ? $comment : 'simple-vpn '.$username,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{username: string, remote_address: string, nat_rule: ?array<string, string>, script: ?string}
     */
    private function createLocked(array $input): array
    {
        $secrets = $this->router->collection('ppp/secret');

        foreach ($secrets as $secret) {
            if (strcasecmp((string) ($secret['name'] ?? ''), $input['username']) === 0) {
                throw new InvalidArgumentException('Username tersebut sudah ada di CHR.');
            }
        }

        $profile = $this->findByName(
            $this->router->collection('ppp/profile'),
            $this->config->pppProfile,
        );
        $pool = $this->findByName(
            $this->router->collection('ip/pool'),
            $this->config->ipPool,
        );

        if ($profile === null || $pool === null) {
            throw new RuntimeException('PPP profile atau IP pool yang diatur pada .env tidak ditemukan di CHR.');
        }

        $ranges = (string) ($pool['ranges'] ?? '');
        if ($ranges === '') {
            throw new RuntimeException('IP pool di CHR tidak memiliki rentang alamat yang dapat digunakan.');
        }

        $active = $this->router->collection('ppp/active');
        $addresses = $this->router->collection('ip/address');
        $remoteAddress = $this->allocateRemoteAddress($ranges, $secrets, $active, $addresses);
        $natRules = [];
        $publicPort = null;

        if ($input['protocol'] !== null) {
            if (filter_var($this->config->publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                throw new RuntimeException('ROUTEROS_PUBLIC_IP harus berisi IPv4 yang valid untuk DST-NAT.');
            }

            $natRules = $this->router->collection('ip/firewall/nat');
            $publicPort = $this->allocatePublicPort($natRules, $input['protocol']);
        }

        $secretId = null;
        $natId = null;
        $secretAttempted = false;
        $natAttempted = false;
        $natAttributes = null;

        try {
            $secretAttempted = true;
            $secretResponse = $this->router->create('ppp/secret', [
                'name' => $input['username'],
                'password' => $input['password'],
                'service' => 'sstp',
                'profile' => $this->config->pppProfile,
                'local-address' => $this->config->localAddress,
                'remote-address' => $remoteAddress,
                'disabled' => 'false',
            ]);
            $secretId = $this->recordId($secretResponse);

            $createdSecret = $this->findSecret(
                $this->router->collection('ppp/secret'),
                $input['username'],
                $this->config->pppProfile,
                $this->config->localAddress,
                $remoteAddress,
            );
            $secretId ??= is_string($createdSecret['.id'] ?? null)
                ? $createdSecret['.id']
                : null;

            if ($createdSecret === null || $secretId === null) {
                throw new RuntimeException('RouterOS belum mengonfirmasi pembuatan PPP Secret.');
            }

            $natRule = null;
            $script = null;

            if ($input['protocol'] !== null && $publicPort !== null) {
                $natAttributes = [
                    'chain' => 'dstnat',
                    'action' => 'dst-nat',
                    'protocol' => $input['protocol'],
                    'dst-address' => $this->config->publicIp,
                    'dst-port' => (string) $publicPort,
                    'to-addresses' => $remoteAddress,
                    'to-ports' => (string) $input['internal_port'],
                    'comment' => $input['comment'],
                    'disabled' => 'false',
                ];
                $natAttempted = true;
                $natResponse = $this->router->create('ip/firewall/nat', $natAttributes);
                $natId = $this->recordId($natResponse);
                $createdNat = $this->findNatRule($this->router->collection('ip/firewall/nat'), $natAttributes);
                $natId ??= is_string($createdNat['.id'] ?? null) ? $createdNat['.id'] : null;

                if ($createdNat === null || $natId === null) {
                    throw new RuntimeException('RouterOS belum mengonfirmasi pembuatan DST-NAT.');
                }

                $natRule = array_map('strval', $natAttributes);
                $script = (new ScriptExporter())->natRule($natRule);
            }
        } catch (RuntimeException $exception) {
            $rollbackFailed = false;

            if ($natAttempted && $natId === null && $natAttributes !== null) {
                try {
                    $createdNat = $this->findNatRule(
                        $this->router->collection('ip/firewall/nat'),
                        $natAttributes,
                    );
                    $natId = is_string($createdNat['.id'] ?? null) ? $createdNat['.id'] : null;
                } catch (RuntimeException) {
                    $rollbackFailed = true;
                }
            }

            if ($natId !== null) {
                try {
                    $this->router->delete('ip/firewall/nat', $natId);
                } catch (RuntimeException) {
                    $rollbackFailed = true;
                }
            }

            if ($secretAttempted && $secretId === null) {
                try {
                    $createdSecret = $this->findSecret(
                        $this->router->collection('ppp/secret'),
                        $input['username'],
                        $this->config->pppProfile,
                        $this->config->localAddress,
                        $remoteAddress,
                    );
                    $secretId = is_string($createdSecret['.id'] ?? null) ? $createdSecret['.id'] : null;
                } catch (RuntimeException) {
                    $rollbackFailed = true;
                }
            }

            if ($secretId !== null) {
                try {
                    $this->router->delete('ppp/secret', $secretId);
                } catch (RuntimeException) {
                    $rollbackFailed = true;
                }
            }

            if ($rollbackFailed) {
                throw new RuntimeException('Provisioning gagal dan rollback tidak terkonfirmasi. Periksa secret/NAT di CHR secara manual.', previous: $exception);
            }

            throw new RuntimeException('Provisioning gagal: '.$exception->getMessage(), previous: $exception);
        }

        return [
            'username' => $input['username'],
            'remote_address' => $remoteAddress,
            'nat_rule' => $natRule,
            'script' => $script,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>|null
     */
    private function findByName(array $records, string $name): ?array
    {
        foreach ($records as $record) {
            if (($record['name'] ?? null) === $name) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>|null
     */
    private function findSecret(
        array $records,
        string $username,
        string $profile,
        string $localAddress,
        string $remoteAddress,
    ): ?array
    {
        foreach ($records as $record) {
            if (strcasecmp((string) ($record['name'] ?? ''), $username) === 0
                && ($record['service'] ?? null) === 'sstp'
                && ($record['profile'] ?? null) === $profile
                && ($record['local-address'] ?? null) === $localAddress
                && ($record['remote-address'] ?? null) === $remoteAddress) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>|null
     */
    private function findNatRule(array $records, array $attributes): ?array
    {
        foreach ($records as $record) {
            $matches = true;
            foreach (['chain', 'action', 'protocol', 'dst-address', 'dst-port', 'to-addresses', 'to-ports', 'comment'] as $key) {
                if ((string) ($record[$key] ?? '') !== $attributes[$key]) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $secrets
     * @param  list<array<string, mixed>>  $active
     * @param  list<array<string, mixed>>  $addresses
     */
    private function allocateRemoteAddress(string $ranges, array $secrets, array $active, array $addresses): string
    {
        $used = [];
        foreach ($secrets as $secret) {
            $this->reserveAddress($used, $secret['remote-address'] ?? null);
            $this->reserveAddress($used, $secret['local-address'] ?? null);
        }
        foreach ($active as $session) {
            $this->reserveAddress($used, $session['address'] ?? null);
            $this->reserveAddress($used, $session['local-address'] ?? null);
        }
        foreach ($addresses as $address) {
            $this->reserveAddress($used, $address['address'] ?? null);
        }
        $this->reserveAddress($used, $this->config->localAddress);

        foreach (explode(',', $ranges) as $range) {
            $parts = array_map('trim', explode('-', trim($range), 2));
            $start = $this->ipv4Number($parts[0] ?? '');
            $end = $this->ipv4Number($parts[1] ?? $parts[0] ?? '');

            if ($start === null || $end === null || $start > $end) {
                throw new RuntimeException('Rentang IP pool CHR tidak valid.');
            }

            $candidate = $start;
            for ($attempt = 0; $candidate <= $end && $attempt <= count($used); $attempt++, $candidate++) {
                if (! isset($used[(string) $candidate])) {
                    return long2ip($candidate);
                }
            }
        }

        throw new RuntimeException('Tidak ada alamat IP kosong pada IP pool CHR.');
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function reserveAddress(array &$used, mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $address = explode('/', $value, 2)[0];
        $number = $this->ipv4Number($address);
        if ($number !== null) {
            $used[(string) $number] = true;
        }
    }

    private function ipv4Number(string $address): ?int
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        $number = ip2long($address);

        return $number === false ? null : (int) sprintf('%u', $number);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function allocatePublicPort(array $rules, string $protocol): int
    {
        for ($port = $this->config->portRangeStart; $port <= $this->config->portRangeEnd; $port++) {
            if (! $this->portInUse($rules, $protocol, $port)) {
                return $port;
            }
        }

        throw new RuntimeException('Tidak ada port publik kosong pada rentang yang diatur.');
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function portInUse(array $rules, string $protocol, int $port): bool
    {
        foreach ($rules as $rule) {
            if (($rule['chain'] ?? null) !== 'dstnat'
                || ($rule['action'] ?? null) !== 'dst-nat'
                || filter_var($rule['disabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $ruleProtocol = strtolower((string) ($rule['protocol'] ?? ''));
            if ($ruleProtocol !== '' && $ruleProtocol !== $protocol) {
                continue;
            }

            $destinationAddress = (string) ($rule['dst-address'] ?? '');
            if ($destinationAddress !== '' && $destinationAddress !== $this->config->publicIp) {
                if (! $this->addressContainsPublicIp($destinationAddress)) {
                    continue;
                }
            }

            if (! empty($rule['dst-address-list']) || ! empty($rule['dst-address-type'])) {
                return true;
            }

            $ports = trim((string) ($rule['dst-port'] ?? ''));
            if ($ports === '') {
                return true;
            }

            foreach (explode(',', $ports) as $part) {
                $bounds = array_map('intval', array_map('trim', explode('-', trim($part), 2)));
                $start = $bounds[0] ?? 0;
                $end = $bounds[1] ?? $start;
                if ($port >= $start && $port <= $end) {
                    return true;
                }
            }
        }

        return false;
    }

    private function addressContainsPublicIp(string $range): bool
    {
        [$address, $prefix] = array_pad(explode('/', $range, 2), 2, null);
        $network = $this->ipv4Number($address);
        $publicIp = $this->ipv4Number($this->config->publicIp);

        if ($network === null || $publicIp === null) {
            return false;
        }

        if ($prefix === null) {
            return $network === $publicIp;
        }

        if (! ctype_digit($prefix) || (int) $prefix > 32) {
            return false;
        }

        $blockSize = 2 ** (32 - (int) $prefix);

        return (int) floor($network / $blockSize) === (int) floor($publicIp / $blockSize);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function recordId(array $record): ?string
    {
        return is_string($record['.id'] ?? null) ? $record['.id'] : null;
    }

    /**
     * @return resource
     */
    private function acquireLock()
    {
        $directory = dirname($this->lockPath);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the provisioning lock directory.');
        }

        $lock = fopen($this->lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open the provisioning lock.');
        }

        chmod($this->lockPath, 0600);
        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('Could not lock provisioning; try again.');
        }

        return $lock;
    }
}
