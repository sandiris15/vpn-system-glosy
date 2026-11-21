<?php

namespace Database\Seeders;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use RuntimeException;

class ClusterServerSeeder extends Seeder
{
    public function run(): void
    {
        $primary = Server::query()
            ->where('priority', 0)
            ->orderBy('id')
            ->first();

        if (! $primary) {
            throw new RuntimeException('Create the primary server before seeding cluster nodes.');
        }

        $users = $primary->users()->get();

        foreach (config('managed.additional_servers', []) as $server) {
            $this->seedNode($primary, $users, $server);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $server
     */
    private function seedNode(Server $primary, Collection $users, array $server): void
    {
        foreach (['name', 'host'] as $required) {
            if (! is_string($server[$required] ?? null) || trim($server[$required]) === '') {
                throw new RuntimeException("Each cluster node requires a {$required}.");
            }
        }

        $host = trim((string) $server['host'], '[]');
        $validHost = filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if (! $validHost) {
            throw new RuntimeException('Cluster node hosts must be a hostname or IP address without a scheme or path.');
        }

        $port = (int) ($server['port'] ?? 0);

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Cluster node ports must be between 1 and 65535.');
        }

        if ($host === $primary->host && $port === $primary->port) {
            throw new RuntimeException('A cluster backup cannot reuse the primary server endpoint.');
        }

        $portRangeStart = $server['port_range_start'] ?? null;
        $portRangeEnd = $server['port_range_end'] ?? null;

        if (($portRangeStart === null) !== ($portRangeEnd === null)) {
            throw new RuntimeException('Set both cluster node port range values, or leave both empty.');
        }

        if ($portRangeStart !== null && (
            (int) $portRangeStart < 1
            || (int) $portRangeEnd > 65535
            || (int) $portRangeStart > (int) $portRangeEnd
        )) {
            throw new RuntimeException('Cluster node port ranges must be between 1 and 65535 and ordered.');
        }

        $publicIp = $server['public_ip'] ?? null;

        if ($publicIp !== null && filter_var($publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException('Cluster node public IPs must be valid IPv4 addresses or empty.');
        }

        $node = Server::query()->updateOrCreate(
            ['host' => $host, 'port' => $port],
            [
                'name' => $server['name'],
                'priority' => (int) $server['priority'],
                'username' => $primary->username,
                'password' => $primary->password,
                'ppp_profile' => $primary->ppp_profile,
                'ip_pool' => $primary->ip_pool,
                'public_ip' => $publicIp ?: null,
                'port_range_start' => $portRangeStart ?: null,
                'port_range_end' => $portRangeEnd ?: null,
                'is_active' => true,
                'status' => 'unknown',
            ],
        );

        foreach ($users as $user) {
            $user->servers()->syncWithoutDetaching([$node->getKey()]);
        }
    }
}
