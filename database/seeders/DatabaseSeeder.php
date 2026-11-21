<?php

namespace Database\Seeders;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('managed.initial_admin');
        $server = config('managed.initial_server');

        foreach ([
            'INITIAL_ADMIN_NAME' => $admin['name'],
            'INITIAL_ADMIN_EMAIL' => $admin['email'],
            'INITIAL_ADMIN_PASSWORD' => $admin['password'],
            'INITIAL_SERVER_NAME' => $server['name'],
            'INITIAL_SERVER_HOST' => $server['host'],
            'INITIAL_SERVER_USERNAME' => $server['username'],
            'INITIAL_SERVER_PASSWORD' => $server['password'],
            'INITIAL_SERVER_PPP_PROFILE' => $server['ppp_profile'],
            'INITIAL_SERVER_IP_POOL' => $server['ip_pool'],
        ] as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException("Set {$key} in the untracked .env file before running db:seed.");
            }
        }

        if (! filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('INITIAL_ADMIN_EMAIL must be a valid email address.');
        }

        if (strlen($admin['password']) < 16) {
            throw new RuntimeException('INITIAL_ADMIN_PASSWORD must contain at least 16 characters.');
        }

        $host = trim($server['host'], '[]');
        $validHost = filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if (! $validHost) {
            throw new RuntimeException('INITIAL_SERVER_HOST must be a hostname or IP address without a scheme or path.');
        }

        if ($server['port'] < 1 || $server['port'] > 65535) {
            throw new RuntimeException('INITIAL_SERVER_PORT must be between 1 and 65535.');
        }

        $portRangeStart = $server['port_range_start'];
        $portRangeEnd = $server['port_range_end'];

        if (($portRangeStart === null) !== ($portRangeEnd === null)) {
            throw new RuntimeException('Set both initial server port range values, or leave both empty.');
        }

        if ($portRangeStart !== null && (
            (int) $portRangeStart < 1
            || (int) $portRangeEnd > 65535
            || (int) $portRangeStart > (int) $portRangeEnd
        )) {
            throw new RuntimeException('The initial server port range must be between 1 and 65535 and ordered.');
        }

        $publicIp = $server['public_ip'];

        if ($publicIp !== null && filter_var($publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException('INITIAL_SERVER_PUBLIC_IP must be an IPv4 address or empty.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => strtolower($admin['email'])],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
            ],
        );

        $managedServer = Server::query()->updateOrCreate(
            ['host' => $server['host'], 'port' => $server['port']],
            [
                'name' => $server['name'],
                'priority' => 0,
                'username' => $server['username'],
                'password' => $server['password'],
                'ppp_profile' => $server['ppp_profile'],
                'ip_pool' => $server['ip_pool'],
                'public_ip' => $publicIp ?: null,
                'port_range_start' => $portRangeStart ?: null,
                'port_range_end' => $portRangeEnd ?: null,
                'is_active' => true,
                'status' => 'unknown',
            ],
        );

        $user->servers()->syncWithoutDetaching([$managedServer->getKey()]);

        $this->call(ClusterServerSeeder::class);
    }
}
