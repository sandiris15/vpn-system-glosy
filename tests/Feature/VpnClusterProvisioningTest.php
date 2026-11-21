<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnClusterProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_one_matching_account_on_every_cluster_node(): void
    {
        [$user, $primary, $backup] = $this->createCluster();
        $secrets = [$primary->host => [], $backup->host => []];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets) {
            $host = parse_url($request->url(), PHP_URL_HOST);
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/rest/ppp/secret' || str_starts_with($path, '/rest/ppp/secret/')) {
                if ($request->method() === 'PUT') {
                    $secrets[$host][] = ['.id' => '*'.(count($secrets[$host]) + 1)] + $request->data();

                    return Http::response(['.id' => '*1']);
                }

                if ($request->method() === 'DELETE') {
                    $secrets[$host] = [];

                    return Http::response(null, 204);
                }

                return Http::response($secrets[$host]);
            }

            return match ($path) {
                '/rest/ppp/profile' => Http::response([['name' => 'default', 'local-address' => '10.23.233.1']]),
                '/rest/ip/pool' => Http::response([['name' => 'pool-remote', 'ranges' => '10.23.233.2-10.23.233.10']]),
                '/rest/ppp/active', '/rest/ip/address', '/rest/ip/firewall/nat' => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($user)
            ->post('/vpn-accounts', [
                'username' => 'branch-jakarta',
                'password' => 'strong-vpn-password',
                'confirm' => '1',
            ])
            ->assertRedirect('/vpn-accounts/*1')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $secrets[$primary->host][0]['remote-address'],
            $secrets[$backup->host][0]['remote-address'],
        );
        $this->assertSame('branch-jakarta', $secrets[$primary->host][0]['name']);
        $this->assertSame('branch-jakarta', $secrets[$backup->host][0]['name']);
        $this->assertSame('strong-vpn-password', $secrets[$primary->host][0]['password']);
        $this->assertSame('strong-vpn-password', $secrets[$backup->host][0]['password']);
    }

    public function test_it_rolls_back_the_primary_when_a_backup_cannot_be_provisioned(): void
    {
        [$user, $primary, $backup] = $this->createCluster();
        $secrets = [$primary->host => [], $backup->host => []];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets, $backup) {
            $host = parse_url($request->url(), PHP_URL_HOST);
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/rest/ppp/secret' || str_starts_with($path, '/rest/ppp/secret/')) {
                if ($request->method() === 'PUT' && $host === $backup->host) {
                    return Http::response(['message' => 'temporarily unavailable'], 503);
                }

                if ($request->method() === 'PUT') {
                    $secrets[$host][] = ['.id' => '*1'] + $request->data();

                    return Http::response(['.id' => '*1']);
                }

                if ($request->method() === 'DELETE') {
                    $secrets[$host] = [];

                    return Http::response(null, 204);
                }

                return Http::response($secrets[$host]);
            }

            return match ($path) {
                '/rest/ppp/profile' => Http::response([['name' => 'default', 'local-address' => '10.23.233.1']]),
                '/rest/ip/pool' => Http::response([['name' => 'pool-remote', 'ranges' => '10.23.233.2-10.23.233.10']]),
                '/rest/ppp/active', '/rest/ip/address', '/rest/ip/firewall/nat' => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($user)
            ->from('/vpn-accounts/create')
            ->post('/vpn-accounts', [
                'username' => 'branch-jakarta',
                'password' => 'strong-vpn-password',
                'confirm' => '1',
            ])
            ->assertRedirect('/vpn-accounts/create')
            ->assertSessionHasErrors('provisioning');

        $this->assertSame([], $secrets[$primary->host]);
        $this->assertSame([], $secrets[$backup->host]);
    }

    /**
     * @return array{User, Server, Server}
     */
    private function createCluster(): array
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $primary = $this->createServer('primary.example.test', '203.0.113.10', 0);
        $backup = $this->createServer('backup.example.test', '203.0.113.11', 1);
        $user->servers()->attach([$primary->getKey(), $backup->getKey()]);

        return [$user, $primary, $backup];
    }

    private function createServer(string $host, string $publicIp, int $priority): Server
    {
        return Server::query()->create([
            'name' => $host,
            'priority' => $priority,
            'host' => $host,
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'default',
            'ip_pool' => 'pool-remote',
            'public_ip' => $publicIp,
            'port_range_start' => 1200,
            'port_range_end' => 2000,
        ]);
    }
}
