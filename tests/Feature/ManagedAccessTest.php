<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\Topology;
use App\Models\User;
use Database\Seeders\OperatorAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_an_assigned_cluster_cannot_log_in(): void
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $this->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'correct horse battery staple',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_does_not_require_a_server_selection(): void
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $primary = $this->createServer('Primary CHR');
        $primary->forceFill(['priority' => 0])->save();
        $backup = $this->createServer('Backup CHR');
        $backup->forceFill(['priority' => 1])->save();
        $user->servers()->attach([$primary->getKey(), $backup->getKey()]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/123.0'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'correct horse battery staple',
            ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $loginLog = AuditLog::query()->where('event', 'auth.login')->firstOrFail();
        $this->assertSame($primary->getKey(), $loginLog->server_id);
        $this->assertSame('203.0.113.8', $loginLog->metadata['ip_address']);
        $this->assertSame(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/123.0',
            $loginLog->metadata['user_agent'],
        );

        $this->get('/vpn-accounts/create')
            ->assertOk()
            ->assertViewHas('selectedServer', $primary)
            ->assertViewHas('clusterServerCount', 2);
    }

    public function test_operator_can_log_in_using_username_without_an_email(): void
    {
        $server = $this->createServer('Managed CHR');
        $operator = User::query()->create([
            'name' => 'Operator',
            'email' => null,
            'username' => 'operator',
            'role' => 'operator',
            'password' => 'operator-test-password',
        ]);
        $operator->servers()->attach($server);

        $this->post('/login', [
            'email' => 'operator',
            'password' => 'operator-test-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($operator);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $operator->getKey(),
            'server_id' => $server->getKey(),
            'event' => 'auth.login',
        ]);
    }

    public function test_operator_seeder_copies_active_servers_without_overwriting_existing_password(): void
    {
        $admin = User::query()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'admin-test-password',
        ]);
        $server = $this->createServer('Managed CHR');
        $admin->servers()->attach($server);
        config([
            'managed.operator_username' => 'operator',
            'managed.operator_password' => 'initial-operator-password',
        ]);

        $this->seed(OperatorAccountSeeder::class);
        $operator = User::query()->where('username', 'operator')->firstOrFail();

        $this->assertSame('operator', $operator->role);
        $this->assertNull($operator->email);
        $this->assertTrue(Hash::check('initial-operator-password', $operator->password));
        $this->assertSame([$server->getKey()], $operator->servers()->pluck('servers.id')->all());

        config(['managed.operator_password' => 'replacement-operator-password']);
        $this->seed(OperatorAccountSeeder::class);

        $this->assertTrue(Hash::check('initial-operator-password', $operator->fresh()->password));
    }

    public function test_dashboard_reads_router_status_without_rendering_ppp_passwords(): void
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $server = $this->createServer('Managed CHR');
        $backup = $this->createServer('Backup CHR');
        $backup->forceFill([
            'priority' => 1,
            'host' => 'backup.example.test',
        ])->save();
        $user->servers()->attach([$server, $backup]);
        AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'event' => 'vpn_account.created',
            'subject' => 'branch-01',
            'metadata' => ['remote_address' => '10.10.0.2'],
        ]);
        AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'event' => 'auth.login',
            'subject' => 'Operator',
            'metadata' => [
                'ip_address' => '203.0.113.9',
                'user_agent' => 'Chrome on macOS',
            ],
        ]);
        $olderTopology = Topology::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Topologi lama',
            'password_hash' => Hash::make('password-lama'),
            'design' => [['type' => 'text', 'x' => 100, 'y' => 100, 'text' => 'Lama']],
        ]);
        $olderTopology->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()])->save();
        $latestTopology = Topology::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Topologi terbaru',
            'password_hash' => Hash::make('password-baru'),
            'design' => [['type' => 'rectangle', 'x' => 500, 'y' => 300, 'text' => 'Router kantor']],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://router.example.test/rest/ppp/secret' => Http::response([
                [
                    '.id' => '*1',
                    'name' => 'branch-01',
                    'password' => 'router-secret-must-not-render',
                    'remote-address' => '10.10.0.2',
                ],
            ]),
            'https://router.example.test/rest/ppp/active' => Http::response([
                ['name' => 'branch-01', 'uptime' => '1h2m', 'address' => '10.10.0.2'],
            ]),
            'https://router.example.test/rest/ip/firewall/nat' => Http::response([
                [
                    'chain' => 'dstnat',
                    'action' => 'dst-nat',
                    'protocol' => 'tcp',
                    'dst-port' => '8080',
                    'to-addresses' => '10.10.0.2',
                    'to-ports' => '80',
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('stats', [
                'total_vpn' => 1,
                'active_vpn' => 1,
                'offline_vpn' => 0,
                'total_nat' => 1,
            ])
            ->assertViewHas('clusterServers', fn ($servers): bool => $servers->count() === 2)
            ->assertSee('Managed CHR')
            ->assertSee('Dashboard VPN')
            ->assertSee('Diskusi terbaru')
            ->assertSeeInOrder([
                'Workspace terbaru',
                'Topologi terakhir dibuat',
                'Diskusi terbaru',
                'Status akun VPN terbaru',
                'Notifikasi aktivitas',
            ])
            ->assertSeeInOrder([
                'Topologi, Interface &amp; VLAN',
                'Template Hotspot',
                'Setup MikroTik Baru',
                'Kumpulan Script Umum',
                'Auto ON/OFF VLAN',
                'Auto ON/OFF Ethernet',
                'Auto Failover Route ISP',
            ], false)
            ->assertSee('Topologi terbaru')
            ->assertSee('Router kantor')
            ->assertDontSee('Topologi lama')
            ->assertDontSee($latestTopology->password_hash)
            ->assertSee('href="'.route('workspaces.topology.show', $latestTopology).'"', false)
            ->assertSee('branch-01')
            ->assertViewHas('activityLogs', fn ($logs): bool => $logs->contains(
                fn (array $activity): bool => $activity['description'] === 'branch-01 · IP 10.10.0.2',
            ))
            ->assertViewHas('activityLogs', fn ($logs): bool => $logs->contains(
                fn (array $activity): bool => $activity['action'] === 'Login berhasil'
                    && $activity['description'] === 'IP asal 203.0.113.9 · Perangkat/browser Chrome on macOS',
            ))
            ->assertDontSee('router-secret-must-not-render');

        $user->forceFill(['role' => 'operator'])->save();
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Kumpulan Script Umum')
            ->assertDontSee('Setup MikroTik Baru');

        Topology::query()->delete();
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Belum ada topologi tersimpan.');
    }

    private function createServer(string $name): Server
    {
        return Server::query()->create([
            'name' => $name,
            'host' => 'router.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
        ]);
    }
}
