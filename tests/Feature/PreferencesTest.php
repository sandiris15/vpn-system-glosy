<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_preferences_and_serves_a_validated_upload_privately(): void
    {
        $user = $this->createAuthorizedUser();
        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['selected_server_id' => $user->servers()->first()->getKey()])
            ->put('/preferences', [
                'wallpaper' => 'rose-dawn',
                'wallpaper_upload' => UploadedFile::fake()->image('background.png', 120, 80),
                'blur' => '12',
                'overlay' => '35',
            ])
            ->assertRedirect('/preferences')
            ->assertSessionHas('status');

        $preference = $user->fresh()->preference;

        $this->assertSame('custom', $preference->wallpaper_id);
        $this->assertSame(12, $preference->blur);
        $this->assertSame(35, $preference->overlay);
        Storage::disk('local')->assertExists($preference->wallpaper_upload_path);

        $this->get('/preferences/wallpaper')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_it_rejects_non_image_uploads_and_invalid_display_values(): void
    {
        $user = $this->createAuthorizedUser();
        Storage::fake('local');

        $this->from('/preferences')
            ->actingAs($user)
            ->withSession(['selected_server_id' => $user->servers()->first()->getKey()])
            ->put('/preferences', [
                'wallpaper' => 'rose-dawn',
                'wallpaper_upload' => UploadedFile::fake()->create('payload.svg', 20, 'image/svg+xml'),
                'blur' => '99',
                'overlay' => '45',
            ])
            ->assertRedirect('/preferences')
            ->assertSessionHasErrors(['wallpaper_upload', 'blur']);

        $this->assertDatabaseCount('user_preferences', 0);
    }

    public function test_uploaded_wallpapers_are_not_shared_between_users(): void
    {
        $user = $this->createAuthorizedUser();
        $otherUser = User::query()->create([
            'name' => 'Other operator',
            'email' => 'other@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $server = $user->servers()->first();
        $otherUser->servers()->attach($server);
        Storage::fake('local');
        $path = $user->preference()->create([
            'wallpaper_id' => 'custom',
            'wallpaper_upload_path' => 'wallpapers/'.$user->getKey().'/secret.png',
            'blur' => 8,
            'overlay' => 45,
        ])->wallpaper_upload_path;
        Storage::disk('local')->put($path, 'not-an-image');

        $this->actingAs($otherUser)
            ->withSession(['selected_server_id' => $server->getKey()])
            ->get('/preferences/wallpaper')
            ->assertNotFound();
    }

    private function createAuthorizedUser(): User
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'correct horse battery staple',
        ]);
        $server = Server::query()->create([
            'name' => 'Managed CHR',
            'host' => 'router.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
        ]);
        $user->servers()->attach($server);

        return $user;
    }
}
