<?php

namespace Tests\Feature;

use App\Models\ForumTopic;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Template Hotspot')
            ->assertSee('Forum Diskusi')
            ->assertSee('Topologi, Interface & VLAN')
            ->assertSee('Setup MikroTik & Port Publik');

        $this->get('/workspaces/hotspot-template')
            ->assertOk()
            ->assertSee('Clean Connect')
            ->assertSee('Promo Harian');

        $this->get('/workspaces/isp-failover')
            ->assertOk()
            ->assertSee('Health-check gateway');
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
}
