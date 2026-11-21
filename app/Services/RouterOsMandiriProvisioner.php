<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UnexpectedValueException;

class RouterOsMandiriProvisioner
{
    /**
     * @param  array{host: string, port: int, username: string, password: string, resolved_ip: ?string}  $connection
     * @param  array{vpn_username: string, vpn_password: string, local_address: string, remote_address: string, sstp_port: int, add_default_route: bool}  $input
     * @return array{username: string, script: string}
     */
    public function provision(array $connection, array $input): array
    {
        $lockKey = 'routeros:mandiri:'.hash('sha256', $connection['host'].':'.$connection['port']);

        return Cache::lock($lockKey, 120)
            ->block(10, fn (): array => $this->provisionLocked($connection, $input));
    }

    /**
     * @param  array{host: string, port: int, username: string, password: string, resolved_ip: ?string}  $connection
     * @param  array{vpn_username: string, vpn_password: string, local_address: string, remote_address: string, sstp_port: int, add_default_route: bool}  $input
     * @return array{username: string, script: string}
     */
    private function provisionLocked(array $connection, array $input): array
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $input['vpn_password']) === 1) {
            throw new InvalidArgumentException('Password tidak boleh mengandung karakter kontrol.');
        }

        $router = RouterOsRestClient::forTemporaryConnection(
            $connection['host'],
            $connection['port'],
            $connection['username'],
            $connection['password'],
            $connection['resolved_ip'],
        );
        $secrets = $router->collection('ppp/secret');

        if ($secrets->contains(fn (array $secret): bool => strcasecmp(
            (string) data_get($secret, 'name', ''),
            $input['vpn_username'],
        ) === 0)) {
            throw new InvalidArgumentException('Username tersebut sudah ada pada router tujuan.');
        }

        $profileExists = $router->collection('ppp/profile')
            ->contains(fn (array $profile): bool => data_get($profile, 'name') === 'default-encryption');

        if (! $profileExists) {
            throw new InvalidArgumentException('PPP profile default-encryption tidak tersedia pada router.');
        }

        $reservedAddresses = $secrets->flatMap(fn (array $secret): array => [
            data_get($secret, 'local-address'),
            data_get($secret, 'remote-address'),
        ])->merge($router->collection('ppp/active')->flatMap(fn (array $session): array => [
            data_get($session, 'local-address'),
            data_get($session, 'address'),
        ]))->merge($router->collection('ip/address')->map(
            fn (array $address): ?string => is_string(data_get($address, 'address'))
                ? explode('/', data_get($address, 'address'))[0]
                : null,
        ));

        foreach ([$input['local_address'], $input['remote_address']] as $address) {
            if ($reservedAddresses->contains(fn (mixed $reserved): bool => $this->addressMatches($reserved, $address))) {
                throw new InvalidArgumentException("Alamat {$address} sudah dipakai pada router.");
            }
        }

        $marker = 'sstp-mandiri:'.Str::uuid();
        $started = false;

        try {
            $started = true;
            $router->create('ppp/secret', [
                'name' => $input['vpn_username'],
                'password' => $input['vpn_password'],
                'service' => 'sstp',
                'profile' => 'default-encryption',
                'local-address' => $input['local_address'],
                'remote-address' => $input['remote_address'],
                'comment' => $marker,
                'disabled' => 'false',
            ]);

            $created = $router->collection('ppp/secret')->first(
                fn (array $secret): bool => data_get($secret, 'comment') === $marker
                    && data_get($secret, 'remote-address') === $input['remote_address'],
            );

            if (! is_array($created) || ! is_string($created['.id'] ?? null)) {
                throw new UnexpectedValueException('RouterOS tidak mengembalikan PPP secret untuk verifikasi.');
            }
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            if ($started) {
                try {
                    $this->removeByMarker($router, $marker);
                } catch (ConnectionException|RequestException|UnexpectedValueException $cleanupException) {
                    Log::critical('Mandiri RouterOS rollback failed.', [
                        'exception' => $cleanupException::class,
                    ]);

                    throw new RouterOsProvisioningException(
                        'Provisioning mungkin belum bersih pada router. Periksa PPP secret secara manual.',
                        previous: $cleanupException,
                    );
                }
            }

            throw $exception;
        }

        return [
            'username' => $input['vpn_username'],
            'script' => (new MandiriSstpScriptGenerator())->generate($connection, $input),
        ];
    }

    private function addressMatches(mixed $reserved, string $address): bool
    {
        if (! is_string($reserved)) {
            return false;
        }

        $reserved = explode('/', $reserved, 2)[0];

        if (str_contains($reserved, '-')) {
            [$start, $end] = explode('-', $reserved, 2);

            if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ip2long($address) >= ip2long($start) && ip2long($address) <= ip2long($end);
            }
        }

        return $reserved === $address;
    }

    private function removeByMarker(RouterOsRestClient $router, string $marker): void
    {
        $secrets = $router->collection('ppp/secret')
            ->filter(fn (array $secret): bool => data_get($secret, 'comment') === $marker);

        foreach ($secrets as $secret) {
            $id = $secret['.id'] ?? null;

            if (! is_string($id)) {
                throw new UnexpectedValueException('RouterOS returned a PPP secret without an item identifier.');
            }

            $router->delete('ppp/secret', $id);
        }

        if ($router->collection('ppp/secret')
            ->contains(fn (array $secret): bool => data_get($secret, 'comment') === $marker)) {
            throw new UnexpectedValueException('RouterOS did not remove the partial PPP secret.');
        }
    }
}
