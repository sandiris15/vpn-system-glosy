<?php

namespace App\Services;

use InvalidArgumentException;

class RouterOsHostResolver
{
    /**
     * @return array{host: string, resolved_ip: ?string}
     */
    public function resolvePublicHost(string $input): array
    {
        $host = trim(trim($input), '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host);

            return ['host' => $host, 'resolved_ip' => null];
        }

        $host = rtrim(strtolower($host), '.');

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! str_contains($host, '.')) {
            throw new InvalidArgumentException('Enter a public IP address or fully qualified hostname.');
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records) || $records === []) {
            throw new InvalidArgumentException('The hostname did not resolve to a public address.');
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($address)) {
                continue;
            }

            $this->assertPublicIp($address);
            $addresses[] = $address;
        }

        if ($addresses === []) {
            throw new InvalidArgumentException('The hostname did not resolve to a public address.');
        }

        return ['host' => $host, 'resolved_ip' => $addresses[0]];
    }

    private function assertPublicIp(string $address): void
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            throw new InvalidArgumentException('Router hosts must resolve only to public IP addresses.');
        }
    }
}
