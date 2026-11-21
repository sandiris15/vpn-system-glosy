<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MandiriSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_connects_to_a_public_router_and_keeps_credentials_only_in_the_short_lived_session(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://8.8.8.8/rest/system/identity' => Http::response([['name' => 'chr-mandiri']]),
        ]);

        $this->post('/mandiri/connection', [
            'host' => '8.8.8.8',
            'port' => '443',
            'username' => 'temporary-user',
            'router_password' => 'temporary-router-secret',
            'verify_tls' => '1',
        ])
            ->assertRedirect('/mandiri/provision')
            ->assertSessionHas('mandiri_connection.username', 'temporary-user')
            ->assertSessionMissing('_old_input.router_password');

        $this->assertGreaterThan(now()->timestamp, session('mandiri_connection.expires_at'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://8.8.8.8/rest/system/identity'
            && $request->method() === 'GET');
    }

    public function test_it_rejects_private_router_addresses_and_requires_tls_verification(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->from('/mandiri/connection')
            ->post('/mandiri/connection', [
                'host' => '127.0.0.1',
                'port' => '443',
                'username' => 'temporary-user',
                'router_password' => 'temporary-router-secret',
                'verify_tls' => '1',
            ])
            ->assertRedirect('/mandiri/connection')
            ->assertSessionHasErrors('host')
            ->assertSessionMissing('_old_input.router_password');

        $this->from('/mandiri/connection')
            ->post('/mandiri/connection', [
                'host' => '8.8.8.8',
                'port' => '443',
                'username' => 'temporary-user',
                'router_password' => 'temporary-router-secret',
            ])
            ->assertRedirect('/mandiri/connection')
            ->assertSessionHasErrors('verify_tls');

        Http::assertNothingSent();
    }

    public function test_it_rejects_bad_credentials_without_flashing_the_router_password(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://8.8.8.8/rest/system/identity' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        $this->from('/mandiri/connection')
            ->post('/mandiri/connection', [
                'host' => '8.8.8.8',
                'port' => '443',
                'username' => 'temporary-user',
                'router_password' => 'temporary-router-secret',
                'verify_tls' => '1',
            ])
            ->assertRedirect('/mandiri/connection')
            ->assertSessionHasErrors('connection')
            ->assertSessionMissing('_old_input.router_password')
            ->assertSessionMissing('mandiri_connection');
    }

    public function test_it_provisions_and_returns_a_private_sstp_script_without_storing_router_credentials_in_database(): void
    {
        $secrets = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets) {
            $url = $request->url();

            if (str_ends_with($url, '/rest/ppp/secret')) {
                if ($request->method() === 'PUT') {
                    $secrets[] = ['.id' => '*1'] + $request->data();

                    return Http::response(['.id' => '*1']);
                }

                return Http::response($secrets);
            }

            return match (true) {
                str_ends_with($url, '/rest/ppp/profile') => Http::response([['name' => 'default-encryption']]),
                str_ends_with($url, '/rest/ppp/active'),
                str_ends_with($url, '/rest/ip/address') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->withSession([
            'mandiri_connection' => [
                'host' => '8.8.8.8',
                'port' => 443,
                'username' => 'temporary-user',
                'password' => 'temporary-router-secret',
                'resolved_ip' => null,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ],
        ])->post('/mandiri/provision', [
            'vpn_username' => 'branch-mandiri',
            'vpn_password' => 'vpn-account-secret',
            'local_address' => '10.20.0.1',
            'remote_address' => '10.20.0.2',
            'sstp_port' => '443',
            'add_default_route' => '1',
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('connect-to="8.8.8.8"')
            ->assertSee('password="vpn-account-secret"')
            ->assertDontSee('temporary-router-secret');

        $this->assertSame('branch-mandiri', $secrets[0]['name']);
        $this->assertSame('10.20.0.2', $secrets[0]['remote-address']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_expires_and_clears_the_mandiri_session(): void
    {
        $this->withSession([
            'mandiri_connection' => [
                'host' => '8.8.8.8',
                'port' => 443,
                'username' => 'temporary-user',
                'password' => 'temporary-router-secret',
                'resolved_ip' => null,
                'expires_at' => now()->subSecond()->timestamp,
            ],
        ])->get('/mandiri/provision')
            ->assertRedirect('/mandiri/connection')
            ->assertSessionMissing('mandiri_connection');
    }

    public function test_it_clears_temporary_credentials_when_the_user_ends_the_session(): void
    {
        $this->withSession([
            'mandiri_connection' => [
                'host' => '8.8.8.8',
                'port' => 443,
                'username' => 'temporary-user',
                'password' => 'temporary-router-secret',
                'resolved_ip' => null,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ],
        ])->post('/mandiri/logout')
            ->assertRedirect('/mandiri/connection')
            ->assertSessionMissing('mandiri_connection');
    }
}
