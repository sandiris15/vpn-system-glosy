<?php

namespace Tests\Feature;

use App\Models\ManagedVpnAccount;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['managed.vpn_primary_only' => true]);
    }

    public function test_it_creates_and_verifies_a_vpn_account_on_the_session_selected_chr(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/vpn-accounts/create')
            ->assertOk()
            ->assertSeeText('Pilihan DST-NAT')
            ->assertSeeText('TCP')
            ->assertSeeText('UDP')
            ->assertSeeText('Tanpa DST-NAT')
            ->assertSeeText('Buat akun')
            ->assertDontSeeText('Mulai provisioning');

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
                str_ends_with($url, '/rest/ppp/profile') => Http::response([['name' => 'sstp-profile']]),
                str_ends_with($url, '/rest/ip/pool') => Http::response([
                    ['name' => 'sstp-pool', 'ranges' => '10.10.0.2-10.10.0.5'],
                ]),
                str_ends_with($url, '/rest/ppp/active'),
                str_ends_with($url, '/rest/ip/address'),
                str_ends_with($url, '/rest/ip/firewall/nat') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $response = $this->from('/vpn-accounts/create')
            ->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->post('/vpn-accounts', [
                'username' => 'branch-01',
                'password' => 'strong-vpn-password',
                'confirm' => '1',
            ]);

        $response->assertRedirectContains('/vpn-accounts/')
            ->assertSessionHasNoErrors();
        $this->get('/vpn-accounts')
            ->assertOk()
            ->assertSee('branch-01')
            ->assertSeeText('Akun terbaru dibuat oleh aplikasi')
            ->assertSee('Detail / Script')
            ->assertDontSee('strong-vpn-password');

        $this->assertSame('branch-01', $secrets[0]['name']);
        $this->assertSame('sstp', $secrets[0]['service']);
        $this->assertSame('sstp-profile', $secrets[0]['profile']);
        $this->assertSame('10.10.0.2', $secrets[0]['remote-address']);
        $this->assertSame('10.20.188.1', $secrets[0]['local-address']);
        $this->assertArrayNotHasKey('comment', $secrets[0]);
        $this->assertDatabaseHas('managed_vpn_accounts', [
            'server_id' => $server->getKey(),
            'routeros_id' => '*1',
            'username' => 'branch-01',
            'remote_address' => '10.10.0.2',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vpn_account.created',
            'subject' => 'branch-01',
        ]);
        $this->assertStringNotContainsString('strong-vpn-password', json_encode(
            \App\Models\AuditLog::query()->firstOrFail()->metadata,
        ));
    }

    public function test_primary_only_mode_creates_secret_and_nat_without_contacting_backup(): void
    {
        $server = $this->createServer();
        $server->update(['public_ip' => '163.61.245.2']);
        $this->authorizeServer($server);
        $backup = Server::query()->create([
            'name' => 'CHR Jakarta',
            'host' => 'router-jkt.example.test',
            'port' => 8433,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
            'public_ip' => '163.61.244.246',
            'port_range_start' => 45000,
            'port_range_end' => 45010,
            'priority' => 1,
        ]);
        $this->user->servers()->attach($backup);
        config(['managed.vpn_primary_only' => true]);
        $secrets = [];
        $natRules = [];
        $requestedHosts = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets, &$natRules, &$requestedHosts) {
            $url = $request->url();
            $requestedHosts[] = parse_url($url, PHP_URL_HOST);

            if (str_ends_with($url, '/rest/ppp/secret')) {
                if ($request->method() === 'PUT') {
                    $secrets[] = ['.id' => '*1'] + $request->data();

                    return Http::response(['.id' => '*1']);
                }

                return Http::response($secrets);
            }

            if (str_ends_with($url, '/rest/ip/firewall/nat')) {
                if ($request->method() === 'PUT') {
                    $natRules[] = ['.id' => '*nat1'] + $request->data();

                    return Http::response(['.id' => '*nat1']);
                }

                return Http::response($natRules);
            }

            return match (true) {
                str_ends_with($url, '/rest/ppp/profile') => Http::response([['name' => 'sstp-profile']]),
                str_ends_with($url, '/rest/ip/pool') => Http::response([
                    ['name' => 'sstp-pool', 'ranges' => '10.10.0.2-10.10.0.5'],
                ]),
                str_ends_with($url, '/rest/ppp/active'),
                str_ends_with($url, '/rest/ip/address') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/vpn-accounts/create')
            ->assertOk()
            ->assertSeeText('Mode satu CHR: Secret, alamat IP statis, dan DST-NAT hanya dikelola pada Managed CHR.')
            ->assertSeeText('failover ke node cadangan belum diaktifkan');

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->followingRedirects()
            ->post('/vpn-accounts', [
                'username' => 'primary-only',
                'password' => 'strong-vpn-password',
                'protocol' => 'tcp',
                'public_port' => '45000',
                'internal_port' => '80',
                'confirm' => '1',
            ])
            ->assertOk()
            ->assertSeeText('Generate VPN saja')
            ->assertSeeText('Generate VPN + Speedtest')
            ->assertSeeText('Salin port')
            ->assertSessionHasNoErrors();

        $this->assertSame([$server->host], array_values(array_unique($requestedHosts)));
        $this->assertCount(1, $secrets);
        $this->assertCount(1, $natRules);
        $this->assertSame('163.61.245.2', $natRules[0]['dst-address']);
        $this->assertSame('tcp', $natRules[0]['protocol']);
        $this->assertSame('primary-only-webfig', $natRules[0]['comment']);
        $this->assertArrayNotHasKey('comment', $secrets[0]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vpn_account.created',
            'subject' => 'primary-only',
        ]);
    }

    public function test_it_automatically_skips_public_ports_already_used_for_the_chr_public_ip(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $secrets = [];
        $natRules = [[
            'chain' => 'dstnat',
            'action' => 'dst-nat',
            'protocol' => 'tcp',
            'dst-address' => '203.0.113.10',
            'dst-port' => '45000',
        ]];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets, &$natRules) {
            $url = $request->url();

            if (str_ends_with($url, '/rest/ppp/secret')) {
                if ($request->method() === 'PUT') {
                    $secrets[] = ['.id' => '*1'] + $request->data();

                    return Http::response(['.id' => '*1']);
                }

                return Http::response($secrets);
            }

            if (str_ends_with($url, '/rest/ip/firewall/nat')) {
                if ($request->method() === 'PUT') {
                    $natRules[] = ['.id' => '*nat1'] + $request->data();

                    return Http::response(['.id' => '*nat1']);
                }

                return Http::response($natRules);
            }

            return match (true) {
                str_ends_with($url, '/rest/ppp/profile') => Http::response([['name' => 'sstp-profile']]),
                str_ends_with($url, '/rest/ip/pool') => Http::response([
                    ['name' => 'sstp-pool', 'ranges' => '10.10.0.2-10.10.0.5'],
                ]),
                str_ends_with($url, '/rest/ppp/active'),
                str_ends_with($url, '/rest/ip/address') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->post('/vpn-accounts', [
                'username' => 'branch-02',
                'password' => 'strong-vpn-password',
                'protocol' => 'tcp',
                'public_port' => '45000',
                'internal_port' => '80',
                'confirm' => '1',
            ])
            ->assertRedirectContains('/vpn-accounts/')
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $secrets);
        $this->assertCount(2, $natRules);
        $this->assertSame('45001', $natRules[1]['dst-port']);
        $this->assertSame('branch-02-webfig', $natRules[1]['comment']);
    }

    public function test_it_requires_a_manual_comment_only_for_a_manual_nat_port(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);

        $this->from('/vpn-accounts/create')
            ->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->post('/vpn-accounts', [
                'username' => 'branch-manual',
                'password' => 'strong-vpn-password',
                'protocol' => 'tcp',
                'internal_port' => 'custom',
                'custom_internal_port' => '8080',
                'confirm' => '1',
            ])
            ->assertRedirect('/vpn-accounts/create')
            ->assertSessionHasErrors('manual_comment');
    }

    public function test_it_rolls_back_the_secret_if_nat_creation_fails(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $secrets = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets) {
            $url = $request->url();

            if (str_contains($url, '/rest/ppp/secret')) {
                if ($request->method() === 'PUT') {
                    $secrets[] = ['.id' => '*1'] + $request->data();

                    return Http::response(['.id' => '*1']);
                }

                if ($request->method() === 'DELETE') {
                    $secrets = [];

                    return Http::response(null, 204);
                }

                return Http::response($secrets);
            }

            if (str_ends_with($url, '/rest/ip/firewall/nat')) {
                return $request->method() === 'PUT'
                    ? Http::response(['message' => 'router error'], 500)
                    : Http::response([]);
            }

            return match (true) {
                str_ends_with($url, '/rest/ppp/profile') => Http::response([['name' => 'sstp-profile']]),
                str_ends_with($url, '/rest/ip/pool') => Http::response([
                    ['name' => 'sstp-pool', 'ranges' => '10.10.0.2-10.10.0.5'],
                ]),
                str_ends_with($url, '/rest/ppp/active'),
                str_ends_with($url, '/rest/ip/address') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->from('/vpn-accounts/create')
            ->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->post('/vpn-accounts', [
                'username' => 'branch-03',
                'password' => 'strong-vpn-password',
                'protocol' => 'tcp',
                'public_port' => '45000',
                'internal_port' => '80',
                'confirm' => '1',
            ])
            ->assertRedirect('/vpn-accounts/create')
            ->assertSessionHasErrors('provisioning');

        $this->assertSame([], $secrets);
    }

    public function test_it_shows_copyable_customer_access_data_with_nat_ports(): void
    {
        $server = $this->createServer();
        $server->update(['public_ip' => '163.61.245.2']);
        $this->authorizeServer($server);
        $secondaryServer = Server::query()->create([
            'name' => 'CHR Jakarta',
            'host' => 'router-jkt.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
            'public_ip' => '163.61.244.246',
            'port_range_start' => 45000,
            'port_range_end' => 45010,
            'priority' => 1,
        ]);
        $this->user->servers()->attach($secondaryServer);
        $remoteServer = Server::query()->create([
            'name' => 'Router-Remote-JKT',
            'host' => 'remote-jkt.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
            'public_ip' => '103.13.235.10',
            'port_range_start' => 45000,
            'port_range_end' => 45010,
            'priority' => 2,
        ]);
        $this->user->servers()->attach($remoteServer);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                str_ends_with($url, '/rest/ppp/secret') => Http::response([$this->managedSecret()]),
                str_ends_with($url, '/rest/ppp/active') => Http::response([]),
                str_ends_with($url, '/rest/ip/firewall/nat') => Http::response([[
                    'chain' => 'dstnat',
                    'action' => 'dst-nat',
                    'protocol' => 'tcp',
                    'to-addresses' => '10.10.0.5',
                    'dst-port' => '8199',
                    'to-ports' => '80',
                    'comment' => 'sstp-console:branch-04:managed-token',
                    'disabled' => 'false',
                ]]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/vpn-accounts/*1')
            ->assertOk()
            ->assertSeeText('Halo, berikut adalah informasi akses VPN & Port Forwarding Anda VIA DATA CENTER PT NAT:')
            ->assertSeeText('👤 Username: branch-04')
            ->assertSeeText('🌐 IP Publik Server:')
            ->assertSeeText('- Managed CHR: 163.61.245.2')
            ->assertDontSeeText('CHR Jakarta: 163.61.244.246')
            ->assertDontSeeText('Router-Remote-JKT: 103.13.235.10')
            ->assertSeeText('Port WWW (Web)')
            ->assertSeeText('Protokol: TCP')
            ->assertSeeText('Managed CHR: 163.61.245.2:8199')
            ->assertDontSeeText('CHR Jakarta: 163.61.244.246:8199')
            ->assertDontSeeText('Router-Remote-JKT: 103.13.235.10:8199')
            ->assertSeeText('Comment: sstp-console:branch-04:managed-token')
            ->assertSeeText('Silakan disimpan dengan baik. Terima kasih!');
    }

    public function test_it_generates_a_router_script_only_for_a_managed_account(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $secret = $this->managedSecret();
        unset($secret['comment']);
        ManagedVpnAccount::query()->create([
            'server_id' => $server->getKey(),
            'routeros_id' => '*1',
            'username' => 'branch-04',
            'remote_address' => '10.10.0.5',
            'nat_rule_ids' => [],
        ]);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($secret) {
            return match (true) {
                str_ends_with($request->url(), '/rest/ppp/secret') => Http::response([$secret]),
                str_ends_with($request->url(), '/rest/ppp/active'),
                str_ends_with($request->url(), '/rest/ip/firewall/nat') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->post('/vpn-accounts/*1/script')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('verify-server-certificate=no')
            ->assertSee('dst-address-list="speedtest"')
            ->assertSee('www.speedtest.net')
            ->assertSee('IP-Lokal')
            ->assertSee('connect-to="router.example.test"')
            ->assertDontSee('connect-to="203.0.113.10"')
            ->assertSee('password="vpn-script-secret"');

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->post('/vpn-accounts/*1/script', ['script_mode' => 'vpn'])
            ->assertOk()
            ->assertSee('Preview script RouterOS v7 — VPN saja')
            ->assertSee('verify-server-certificate=no')
            ->assertSee('connect-to="router.example.test"')
            ->assertSee('password="vpn-script-secret"')
            ->assertDontSee('/routing table')
            ->assertDontSee('/ip firewall address-list')
            ->assertDontSee('/ip firewall mangle')
            ->assertDontSee('/ip route')
            ->assertDontSee('/ip firewall nat');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vpn_account.script_generated',
            'subject' => 'branch-04',
        ]);
    }

    public function test_external_sstp_accounts_can_generate_scripts_but_cannot_be_edited_or_deleted(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $secret = $this->managedSecret();
        unset($secret['comment']);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($secret) {
            return match (true) {
                str_ends_with($request->url(), '/rest/ppp/secret') => Http::response([$secret]),
                str_ends_with($request->url(), '/rest/ppp/active'),
                str_ends_with($request->url(), '/rest/ip/firewall/nat') => Http::response([]),
                default => Http::response([], 404),
            };
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/vpn-accounts')
            ->assertOk()
            ->assertSeeText('Akun di luar aplikasi')
            ->assertSeeText('Generate script')
            ->assertDontSeeText('Ubah')
            ->assertDontSeeText('Hapus');

        $this->get('/vpn-accounts/*1/edit')->assertNotFound();
        $this->put('/vpn-accounts/*1', [
            'password' => 'new-external-password',
            'password_confirmation' => 'new-external-password',
        ])->assertNotFound();
        $this->delete('/vpn-accounts/*1')->assertNotFound();

        $this->post('/vpn-accounts/*1/script', ['script_mode' => 'vpn'])
            ->assertOk()
            ->assertSee('Detail PPP Secret SSTP')
            ->assertSee('Akun ini dibuat di luar aplikasi')
            ->assertSee('password="vpn-script-secret"')
            ->assertDontSee('/routing table');
    }

    public function test_operator_can_add_accounts_but_cannot_edit_or_delete_them(): void
    {
        $server = $this->createServer();
        $operator = User::query()->create([
            'name' => 'Operator',
            'email' => null,
            'username' => 'operator',
            'role' => 'operator',
            'password' => 'operator-test-password',
        ]);
        $operator->servers()->attach($server);

        $accountService = \Mockery::mock(\App\Services\RouterOsClusterVpnAccountService::class);
        $accountService->shouldReceive('provision')->once()->andReturn([
            'id' => '*new',
            'username' => 'branch-new',
            'remote_address' => '10.10.0.6',
            'protocol' => null,
            'public_port' => null,
            'internal_port' => null,
        ]);
        $this->app->instance(\App\Services\RouterOsClusterVpnAccountService::class, $accountService);

        $this->actingAs($operator)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/vpn-accounts/create')
            ->assertOk();

        $this->get('/vpn-accounts/*1/edit')->assertForbidden();
        $this->put('/vpn-accounts/*1', [
            'password' => 'new-external-password',
            'password_confirmation' => 'new-external-password',
        ])->assertForbidden();
        $this->delete('/vpn-accounts/*1')->assertForbidden();

        $this->post('/vpn-accounts', [
            'username' => 'branch-new',
            'password' => 'new-account-password',
            'confirm' => '1',
        ])->assertRedirectContains('/vpn-accounts/')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $operator->getKey(),
            'event' => 'vpn_account.created',
            'subject' => 'branch-new',
        ]);
    }

    public function test_it_updates_the_password_of_a_managed_account(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $secret = $this->managedSecret();
        unset($secret['comment']);
        ManagedVpnAccount::query()->create([
            'server_id' => $server->getKey(),
            'routeros_id' => '*1',
            'username' => 'branch-04',
            'remote_address' => '10.10.0.5',
            'nat_rule_ids' => [],
        ]);
        $updatedPassword = null;
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$updatedPassword, $secret) {
            if (str_contains($request->url(), '/rest/ppp/secret')) {
                if ($request->method() === 'PATCH') {
                    $updatedPassword = $request->data()['password'] ?? null;

                    return Http::response(null, 204);
                }

                return Http::response([$secret]);
            }

            return Http::response([], 404);
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->put('/vpn-accounts/*1', [
                'password' => 'new-vpn-password',
                'password_confirmation' => 'new-vpn-password',
            ])
            ->assertRedirect('/vpn-accounts');

        $this->assertSame('new-vpn-password', $updatedPassword);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vpn_account.password_changed',
            'subject' => 'branch-04',
        ]);
    }

    public function test_it_deletes_only_the_tracked_secret_and_its_nat_rule(): void
    {
        $server = $this->createServer();
        $this->authorizeServer($server);
        $secret = $this->managedSecret();
        unset($secret['comment']);
        ManagedVpnAccount::query()->create([
            'server_id' => $server->getKey(),
            'routeros_id' => '*1',
            'username' => 'branch-04',
            'remote_address' => '10.10.0.5',
            'nat_rule_ids' => ['*nat1'],
        ]);
        $secrets = [$secret];
        $natRules = [[
            '.id' => '*nat1',
            'comment' => 'branch-04-webfig',
        ]];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$secrets, &$natRules) {
            if (str_contains($request->url(), '/rest/ppp/secret')) {
                if ($request->method() === 'DELETE') {
                    $secrets = [];

                    return Http::response(null, 204);
                }

                return Http::response($secrets);
            }

            if (str_contains($request->url(), '/rest/ip/firewall/nat')) {
                if ($request->method() === 'DELETE') {
                    $natRules = [];

                    return Http::response(null, 204);
                }

                return Http::response($natRules);
            }

            return Http::response([], 404);
        });

        $this->actingAs($this->user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->delete('/vpn-accounts/*1')
            ->assertRedirect('/vpn-accounts');

        $this->assertSame([], $secrets);
        $this->assertSame([], $natRules);
        $this->assertDatabaseMissing('managed_vpn_accounts', [
            'server_id' => $server->getKey(),
            'routeros_id' => '*1',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vpn_account.deleted',
            'subject' => 'branch-04',
        ]);
    }

    private User $user;

    private function authorizeServer(Server $server): void
    {
        $this->user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator-'.$server->getKey().'@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $this->user->servers()->attach($server);
    }

    private function createServer(): Server
    {
        return Server::query()->create([
            'name' => 'Managed CHR',
            'host' => 'router.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
            'public_ip' => '203.0.113.10',
            'port_range_start' => 45000,
            'port_range_end' => 45010,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function managedSecret(): array
    {
        return [
            '.id' => '*1',
            'name' => 'branch-04',
            'password' => 'vpn-script-secret',
            'service' => 'sstp',
            'remote-address' => '10.10.0.5',
            'comment' => 'sstp-console:branch-04:managed-token',
        ];
    }
}
