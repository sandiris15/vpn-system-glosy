<?php

namespace Tests\Feature;

use App\Models\ForumTopic;
use App\Models\Server;
use App\Models\Topology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceForumTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspaces_open_active_and_planned_tools(): void
    {
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->get('/workspaces')
            ->assertOk()
            ->assertSee('PPP Secret & Speedtest Script')
            ->assertSee('preview-pill-active', false)
            ->assertSee('Template Hotspot')
            ->assertSeeInOrder(['data-search="template hotspot', 'preview-pill-active', '>Aktif</span>', 'Template Hotspot'], false)
            ->assertSee('Forum Diskusi')
            ->assertSee('Topologi, Interface & VLAN')
            ->assertSee('Auto Failover Route ISP')
            ->assertSee('Auto ON/OFF Ethernet')
            ->assertSee('Auto ON/OFF VLAN')
            ->assertSee('Kumpulan Script Umum')
            ->assertSee('Setup MikroTik Baru')
            ->assertSee('Cari workspace');

        $this->get('/workspaces/hotspot-template')
            ->assertOk()
            ->assertSee('Clean Connect')
            ->assertSee('Clean Connect + Iklan')
            ->assertSee('Gambar iklan bawah')
            ->assertDontSee('Pilihan gaya')
            ->assertDontSee('Username = password')
            ->assertDontSee('Username &amp; password terpisah', false)
            ->assertSee('Gambar popup promo')
            ->assertSee('Judul popup promo')
            ->assertSee('Teks bagian bawah halaman')
            ->assertSee('Daftar paket dan harga')
            ->assertSee('Unduh template ZIP')
            ->assertSee('Voucher')
            ->assertSee('Member')
            ->assertSee('Preview langsung');

        $this->get('/workspaces/isp-failover')
            ->assertOk()
            ->assertSee('Auto Failover Route ISP')
            ->assertSee('Gateway ISP 1')
            ->assertSee('Gateway ISP 2');

        $this->get('/workspaces/ethernet-scheduler')
            ->assertOk()
            ->assertSee('Nama interface Ethernet')
            ->assertSee('Jam ON')
            ->assertSee('Menit ON')
            ->assertSee('Jam OFF')
            ->assertSee('Menit OFF')
            ->assertSee('Sekali pada tanggal tertentu');

        $this->get('/workspaces/vlan-scheduler')
            ->assertOk()
            ->assertSee('Nama interface VLAN');

        $this->get('/workspaces/script-library')
            ->assertOk()
            ->assertSee('Kosongkan alamat PPP Secret')
            ->assertSee('Backup PPP Secret')
            ->assertSee('Nama file backup');

        $this->get('/workspaces/mikrotik-setup')
            ->assertOk()
            ->assertSee('Setup MikroTik Baru')
            ->assertSee('DHCP client')
            ->assertSee('IP lokal LAN')
            ->assertSee('Awal pool IP PPPoE')
            ->assertSee('Awal pool IP Hotspot/DHCP')
            ->assertSee('Password admin baru');
    }

    public function test_topologies_are_viewable_by_users_but_require_their_password_to_edit(): void
    {
        $creator = $this->createUserWithServer();
        $this->actingAs($creator)->post('/workspaces/topology', [
            'name' => 'Jaringan kantor',
            'password' => 'topology-password',
            'password_confirmation' => 'topology-password',
        ])->assertRedirect();

        $topology = Topology::query()->firstOrFail();
        $this->assertNotSame('topology-password', $topology->password_hash);
        $design = [
            ['type' => 'rectangle', 'x' => 100, 'y' => 100, 'text' => 'Router'],
            ['type' => 'line', 'x' => 175, 'y' => 100, 'x2' => 300, 'y2' => 100],
        ];
        $this->putJson('/workspaces/topology/'.$topology->id, ['revision' => 0, 'design' => $design])
            ->assertOk()->assertJsonPath('revision', 1);
        $this->assertSame($design, $topology->fresh()->design);

        $other = $this->createUserWithServer('operator');
        $this->flushSession()->actingAs($other);
        $this->get('/workspaces/topology/'.$topology->id)->assertOk()->assertSee('Router')->assertSee('Password untuk mengedit');
        $this->putJson('/workspaces/topology/'.$topology->id, ['revision' => 1, 'design' => []])->assertForbidden();
        $this->from('/workspaces/topology/'.$topology->id)
            ->post('/workspaces/topology/'.$topology->id.'/unlock', ['password' => 'incorrect'])
            ->assertSessionHasErrors(['password'])
            ->assertSessionMissing('_old_input.password');
        $this->putJson('/workspaces/topology/'.$topology->id, ['revision' => 1, 'design' => []])->assertForbidden();
        $this->post('/workspaces/topology/'.$topology->id.'/unlock', ['password' => 'topology-password'])
            ->assertRedirect('/workspaces/topology/'.$topology->id);
        $this->putJson('/workspaces/topology/'.$topology->id, ['revision' => 0, 'design' => []])
            ->assertStatus(409);
        $this->putJson('/workspaces/topology/'.$topology->id, ['revision' => 1, 'design' => []])
            ->assertOk()->assertJsonPath('revision', 2);
    }

    public function test_topology_rejects_invalid_design_and_password_confirmation(): void
    {
        $user = $this->createUserWithServer();
        $this->actingAs($user)->from('/workspaces/topology')
            ->post('/workspaces/topology', [
                'name' => 'Test',
                'password' => 'topology-password',
                'password_confirmation' => 'different',
            ])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');

        $this->post('/workspaces/topology', [
            'name' => 'Test',
            'password' => 'topology-password',
            'password_confirmation' => 'topology-password',
        ]);
        $topology = Topology::query()->firstOrFail();
        $this->putJson('/workspaces/topology/'.$topology->id, [
            'revision' => 0,
            'design' => [['type' => 'line', 'x' => 0, 'y' => 0, 'x2' => 1200, 'y2' => 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['design.0.x2']);
        $this->assertSame([], $topology->fresh()->design);
    }

    public function test_operator_cannot_access_admin_only_script_workspaces(): void
    {
        $operator = $this->createUserWithServer('operator');

        $this->actingAs($operator)
            ->get('/workspaces')
            ->assertOk()
            ->assertDontSee('Kumpulan Script Umum')
            ->assertDontSee('Setup MikroTik Baru');

        $this->get('/workspaces/script-library')->assertForbidden();
        $this->post('/workspaces/script-library/generate', [
            'script_type' => 'backup-ppp-secrets',
            'backup_file' => 'BackupPPPoE',
        ])->assertForbidden();
        $this->get('/workspaces/mikrotik-setup')->assertForbidden();
        $this->post('/workspaces/mikrotik-setup/script', $this->validMikrotikSetupInput())
            ->assertForbidden();
    }

    public function test_script_library_generates_the_selected_ppp_secret_script(): void
    {
        Http::fake();
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->post('/workspaces/script-library/generate', [
                'script_type' => 'clear-ppp-addresses',
                'confirm_clear' => '1',
            ])
            ->assertRedirect('/workspaces/script-library')
            ->assertSessionHas('generatedLibraryScript', function (string $script): bool {
                return str_contains($script, '/ppp secret set [find] !local-address !remote-address');
            });

        $this->get('/workspaces/script-library')
            ->assertOk()
            ->assertSee('/ppp secret set [find] !local-address !remote-address');

        $this->post('/workspaces/script-library/generate', [
            'script_type' => 'backup-ppp-secrets',
            'backup_file' => 'BackupPPPoE',
        ])
            ->assertRedirect('/workspaces/script-library')
            ->assertSessionHas('generatedLibraryScript', function (string $script): bool {
                return str_contains($script, '/ppp secret export file=BackupPPPoE show-sensitive');
            });

        Http::assertNothingSent();
    }

    public function test_script_library_requires_clear_confirmation_and_safe_backup_file_names(): void
    {
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->from('/workspaces/script-library')
            ->post('/workspaces/script-library/generate', [
                'script_type' => 'clear-ppp-addresses',
            ])
            ->assertRedirect('/workspaces/script-library')
            ->assertSessionHasErrors(['confirm_clear']);

        $this->post('/workspaces/script-library/generate', [
            'script_type' => 'backup-ppp-secrets',
            'backup_file' => 'BackupPPPoE;reboot',
        ])
            ->assertRedirect('/workspaces/script-library')
            ->assertSessionHasErrors(['backup_file']);
    }

    public function test_mikrotik_setup_generates_a_no_store_script_with_security_and_network_configuration(): void
    {
        Http::fake();
        $user = $this->createUserWithServer();
        $adminPassword = 'Example-Admin-Pass-123';

        $response = $this->actingAs($user)
            ->post('/workspaces/mikrotik-setup/script', $this->validMikrotikSetupInput([
                'admin_password' => $adminPassword,
            ]));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('/system identity set name=')
            ->assertSee('/ip dhcp-client')
            ->assertSee('/ip firewall nat')
            ->assertSee('/interface pppoe-server server')
            ->assertSee('/ip hotspot')
            ->assertSee('/ip service set')
            ->assertSee('disabled=yes');

        $content = $response->getContent();
        $this->assertStringContainsString('password=&quot;'.$adminPassword.'&quot;', $content);
        $this->assertStringNotContainsString('value="'.$adminPassword.'"', $content);
        $response->assertSessionMissing('_old_input');
        Http::assertNothingSent();
    }

    public function test_mikrotik_setup_creates_vlan_before_assigning_its_ip(): void
    {
        $user = $this->createUserWithServer();
        $input = $this->validMikrotikSetupInput([
            'lan_mode' => 'vlan',
            'vlan_parent' => 'ether2',
            'vlan_name' => 'vlan100',
            'vlan_id' => 100,
        ]);
        unset($input['lan_interface']);

        $response = $this->actingAs($user)
            ->post('/workspaces/mikrotik-setup/script', $input)
            ->assertOk();

        $script = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5);
        $vlanPosition = strpos($script, '/interface vlan add');
        $addressPosition = strpos($script, '/ip address add');

        $this->assertNotFalse($vlanPosition);
        $this->assertNotFalse($addressPosition);
        $this->assertLessThan($addressPosition, $vlanPosition);
        $this->assertStringContainsString('vlan-id=100', $script);
        $this->assertStringContainsString('interface=vlan100', $script);
    }

    public function test_mikrotik_setup_rejects_overlapping_pools_without_flashing_password(): void
    {
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->from('/workspaces/mikrotik-setup')
            ->post('/workspaces/mikrotik-setup/script', $this->validMikrotikSetupInput([
                'hotspot_pool_start' => '192.168.10.40',
            ]))
            ->assertRedirect('/workspaces/mikrotik-setup')
            ->assertSessionHasErrors(['configuration'])
            ->assertSessionHas('_old_input', function (array $input): bool {
                return ! array_key_exists('admin_password', $input);
            });
    }

    public function test_ethernet_scheduler_generates_a_daily_script_without_applying_it(): void
    {
        Http::fake();
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->post('/workspaces/ethernet-scheduler/script', [
                'interface_name' => 'ether3',
                'schedule_mode' => 'daily',
                'on_hour' => 18,
                'on_minute' => 30,
                'off_hour' => 21,
                'off_minute' => 55,
            ])
            ->assertRedirect('/workspaces/ethernet-scheduler')
            ->assertSessionHas('generatedInterfaceScheduleScript', function (string $script): bool {
                return str_contains($script, 'start-time=18:30:00 interval=1d')
                    && str_contains($script, 'start-time=21:55:00 interval=1d')
                    && str_contains($script, 'on-event="/interface ethernet enable ether3"')
                    && str_contains($script, 'on-event="/interface ethernet disable ether3"')
                    && str_contains($script, 'policy=read,write,test');
            });

        $this->get('/workspaces/ethernet-scheduler')
            ->assertOk()
            ->assertSee('/interface ethernet enable ether3');

        Http::assertNothingSent();
    }

    public function test_vlan_scheduler_generates_a_one_time_script_for_the_selected_dates(): void
    {
        $user = $this->createUserWithServer();
        $onDate = now()->addDay()->toDateString();
        $offDate = now()->addDays(2)->toDateString();

        $this->actingAs($user)
            ->post('/workspaces/vlan-scheduler/script', [
                'interface_name' => 'vlan100',
                'schedule_mode' => 'once',
                'on_date' => $onDate,
                'off_date' => $offDate,
                'on_hour' => 18,
                'on_minute' => 30,
                'off_hour' => 21,
                'off_minute' => 55,
            ])
            ->assertRedirect('/workspaces/vlan-scheduler')
            ->assertSessionHas('generatedInterfaceScheduleScript', function (string $script) use ($onDate, $offDate): bool {
                return str_contains($script, 'start-date='.$onDate.' start-time=18:30:00 interval=0s')
                    && str_contains($script, 'start-date='.$offDate.' start-time=21:55:00 interval=0s')
                    && str_contains($script, 'on-event="/interface vlan enable vlan100"')
                    && str_contains($script, 'on-event="/interface vlan disable vlan100"');
            });
    }

    public function test_interface_scheduler_rejects_unsafe_names_and_out_of_order_one_time_events(): void
    {
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->from('/workspaces/ethernet-scheduler')
            ->post('/workspaces/ethernet-scheduler/script', [
                'interface_name' => 'ether3;system reboot',
                'schedule_mode' => 'daily',
                'on_hour' => 18,
                'on_minute' => 30,
                'off_hour' => 21,
                'off_minute' => 55,
            ])
            ->assertRedirect('/workspaces/ethernet-scheduler')
            ->assertSessionHasErrors(['interface_name']);

        $onDate = now()->addDays(2)->toDateString();
        $offDate = now()->addDay()->toDateString();

        $this->from('/workspaces/vlan-scheduler')
            ->post('/workspaces/vlan-scheduler/script', [
                'interface_name' => 'vlan100',
                'schedule_mode' => 'once',
                'on_date' => $onDate,
                'off_date' => $offDate,
                'on_hour' => 18,
                'on_minute' => 30,
                'off_hour' => 21,
                'off_minute' => 55,
            ])
            ->assertRedirect('/workspaces/vlan-scheduler')
            ->assertSessionHasErrors(['off_date']);
    }

    public function test_failover_workspace_generates_routeros_script_without_applying_it(): void
    {
        Http::fake();
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->post('/workspaces/isp-failover/script', [
                'gateway_isp1' => '192.0.2.1',
                'gateway_isp2' => '198.51.100.1',
            ])
            ->assertRedirect('/workspaces/isp-failover')
            ->assertSessionHas('generatedIspFailoverScript', function (string $script): bool {
                return str_contains($script, 'dst-address=8.8.8.8/32 scope=10 gateway="192.0.2.1"')
                    && str_contains($script, 'dst-address=8.8.4.4/32 scope=10 gateway="192.0.2.1"')
                    && str_contains($script, 'dst-address=1.1.1.1/32 scope=10 gateway="198.51.100.1"')
                    && str_contains($script, 'gateway=8.8.8.8 distance=1')
                    && str_contains($script, 'gateway=8.8.4.4 distance=1')
                    && str_contains($script, 'gateway=1.1.1.1 distance=2')
                    && str_contains($script, 'gateway=1.0.0.1 distance=2')
                    && str_contains($script, 'check-gateway=ping');
            });

        $this->get('/workspaces/isp-failover')
            ->assertOk()
            ->assertSee('dst-address=8.8.8.8/32')
            ->assertSee('192.0.2.1');

        Http::assertNothingSent();
    }

    public function test_failover_workspace_rejects_invalid_or_duplicate_gateways(): void
    {
        $user = $this->createUserWithServer();

        $this->actingAs($user)
            ->from('/workspaces/isp-failover')
            ->post('/workspaces/isp-failover/script', [
                'gateway_isp1' => '192.0.2.1',
                'gateway_isp2' => '192.0.2.1',
            ])
            ->assertRedirect('/workspaces/isp-failover')
            ->assertSessionHasErrors(['gateway_isp1', 'gateway_isp2']);

        $this->from('/workspaces/isp-failover')
            ->post('/workspaces/isp-failover/script', [
                'gateway_isp1' => 'not-an-ip',
                'gateway_isp2' => '198.51.100.1',
            ])
            ->assertRedirect('/workspaces/isp-failover')
            ->assertSessionHasErrors(['gateway_isp1']);
    }

    public function test_authenticated_users_can_create_topics_and_replies_and_admin_can_moderate(): void
    {
        $admin = $this->createUserWithServer();

        $this->actingAs($admin)
            ->post('/forum/topics', [
                'title' => 'VPN status check',
                'body' => 'Diskusikan pemeriksaan koneksi.',
            ])
            ->assertRedirect();

        $topic = ForumTopic::query()->firstOrFail();
        $this->get('/forum/topics/'.$topic->getKey())
            ->assertOk()
            ->assertSee('VPN status check')
            ->assertSee('Diskusikan pemeriksaan koneksi.');

        $this->post('/forum/topics/'.$topic->getKey().'/replies', [
            'body' => 'Saya akan memeriksa log koneksi.',
        ])->assertRedirect();

        $this->get('/forum/topics/'.$topic->getKey())
            ->assertSee('Saya akan memeriksa log koneksi.');

        $this->delete('/forum/topics/'.$topic->getKey())
            ->assertRedirect('/forum');

        $this->assertDatabaseMissing('forum_topics', ['id' => $topic->getKey()]);
        $this->assertDatabaseMissing('forum_replies', ['forum_topic_id' => $topic->getKey()]);
    }

    public function test_operator_can_discuss_but_cannot_moderate_forum_content(): void
    {
        $admin = $this->createUserWithServer();
        $operator = $this->createUserWithServer('operator', 'operator');
        $topic = ForumTopic::query()->create([
            'user_id' => $admin->getKey(),
            'title' => 'Operational notice',
            'body' => 'Review the current VPN state.',
        ]);

        $this->actingAs($operator)
            ->post('/forum/topics/'.$topic->getKey().'/replies', [
                'body' => 'Acknowledged.',
            ])
            ->assertRedirect();

        $reply = $topic->replies()->firstOrFail();
        $this->delete('/forum/topics/'.$topic->getKey())->assertForbidden();
        $this->delete('/forum/replies/'.$reply->getKey())->assertForbidden();

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->getKey()]);
        $this->assertDatabaseHas('forum_replies', ['id' => $reply->getKey()]);
    }

    private function createUserWithServer(string $role = 'admin', string $username = 'operator-test'): User
    {
        static $userCounter = 0;
        $userCounter++;

        $server = Server::query()->create([
            'name' => 'Managed CHR '.$userCounter,
            'host' => 'router-'.$userCounter.'.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
        ]);
        $user = User::query()->create([
            'name' => $role === 'admin' ? 'Administrator' : 'Operator',
            'email' => $role === 'admin' ? 'admin-'.$userCounter.'@example.test' : null,
            'username' => $role === 'admin' ? null : $username,
            'role' => $role,
            'password' => 'operator-test-password',
        ]);
        $user->servers()->attach($server);

        return $user;
    }

    /**
     * @param  array<string, string|int>  $overrides
     * @return array<string, string|int>
     */
    private function validMikrotikSetupInput(array $overrides = []): array
    {
        return array_replace([
            'identity' => 'Router-Cabang-01',
            'wan_interface' => 'ether1',
            'lan_mode' => 'ethernet',
            'lan_interface' => 'ether2',
            'lan_address' => '192.168.10.1/24',
            'pppoe_pool_start' => '192.168.10.2',
            'pppoe_pool_end' => '192.168.10.50',
            'hotspot_pool_start' => '192.168.10.51',
            'hotspot_pool_end' => '192.168.10.200',
            'pppoe_service_name' => 'pppoe',
            'hotspot_server_name' => 'hotspot1',
            'dns_servers' => 'both',
            'admin_username' => 'admin-baru',
            'admin_password' => 'Example-Admin-Pass-123',
        ], $overrides);
    }
}
