<?php

namespace Tests\Feature;

use App\Models\InternshipCertificate;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternshipCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_issue_and_revisit_certificates_with_continuous_numbers(): void
    {
        $user = $this->userWithServer();
        $this->actingAs($user)->get('/workspaces')->assertOk()->assertSee('Sertifikat PKL/MAGANG');
        $this->get('/workspaces/sertifikat-magang')->assertOk()->assertSee('Preview langsung')
            ->assertSee('Nomor resmi ditetapkan setelah sertifikat dibuat.');

        $first = [
            'name' => 'Dewi Anggraini',
            'program' => 'magang',
            'location' => 'Kantor Mempawah',
            'institution' => 'SMK Negeri 1',
            'birth_date' => '2008-05-01',
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-30',
            'issued_date' => '2026-10-31',
        ];
        $this->post('/workspaces/sertifikat-magang', $first)->assertRedirect('/workspaces/sertifikat-magang/1');
        $this->assertDatabaseHas('internship_certificates', ['name' => $first['name'], 'program' => 'magang', 'location' => 'Kantor Mempawah', 'institution' => 'SMK Negeri 1', 'user_id' => $user->id]);
        $this->assertSame('2008-05-01', InternshipCertificate::findOrFail(1)->birth_date->format('Y-m-d'));
        $this->assertSame($first['start_date'], InternshipCertificate::findOrFail(1)->start_date->format('Y-m-d'));
        $this->assertSame($first['end_date'], InternshipCertificate::findOrFail(1)->end_date->format('Y-m-d'));
        $this->get('/workspaces/sertifikat-magang/1')
            ->assertOk()->assertSee('022/Sertifikat/PKL/X/2026')->assertSee('Dewi Anggraini')
            ->assertSee('31 Oktober 2026')->assertSee('Kantor Mempawah')->assertSee('SMK Negeri 1')->assertSee('01 Mei 2008');

        $this->post('/workspaces/sertifikat-magang', array_replace($first, [
            'name' => 'Budi',
            'program' => 'pkl',
            'location' => 'Pontianak',
            'issued_date' => '2027-01-02',
        ]))->assertRedirect('/workspaces/sertifikat-magang/2');
        $this->get('/workspaces/sertifikat-magang')
            ->assertOk()->assertSee('023/Sertifikat/PKL/I/2027')->assertSee('Dewi Anggraini')->assertSee('PKL');
        $this->get('/workspaces/sertifikat-magang/2')->assertOk()->assertSee('Pontianak')->assertSee('pkl');
        $this->assertSame('023/Sertifikat/PKL/I/2027', InternshipCertificate::findOrFail(2)->number);
    }

    public function test_invalid_dates_are_rejected_without_issuing_a_number(): void
    {
        $this->actingAs($this->userWithServer())->from('/workspaces/sertifikat-magang')
            ->post('/workspaces/sertifikat-magang', [
                'name' => '',
                'program' => 'invalid',
                'location' => '',
                'institution' => '',
                'birth_date' => '2999-01-01',
                'start_date' => '2026-10-02',
                'end_date' => '2026-10-01',
                'issued_date' => 'invalid',
            ])->assertSessionHasErrors(['name', 'program', 'location', 'institution', 'birth_date', 'end_date', 'issued_date']);
        $this->assertDatabaseCount('internship_certificates', 0);
    }

    public function test_operator_can_access_history_and_assets_but_guests_cannot(): void
    {
        $this->get('/workspaces/sertifikat-magang')->assertRedirect('/login');
        $this->get('/workspaces/sertifikat-magang/assets/signature')->assertRedirect('/login');

        $this->actingAs($this->userWithServer('operator'));
        $this->post('/workspaces/sertifikat-magang', [
            'name' => 'Peserta',
            'program' => 'pkl',
            'location' => 'Mempawah',
            'institution' => 'SMK Negeri 1',
            'birth_date' => '2008-05-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'issued_date' => '2026-02-02',
        ])->assertRedirect('/workspaces/sertifikat-magang/1');
        $this->get('/workspaces/sertifikat-magang')->assertOk()->assertSee('Peserta');
        $this->get('/workspaces/sertifikat-magang/assets/signature')->assertOk();
        $this->get('/workspaces/sertifikat-magang/assets/other')->assertNotFound();
    }

    public function test_existing_records_keep_their_original_program_and_location(): void
    {
        $user = $this->userWithServer();
        $certificate = InternshipCertificate::query()->create([
            'user_id' => $user->id,
            'name' => 'Peserta lama',
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'issued_date' => '2026-02-02',
        ])->fresh();

        $this->assertSame('magang', $certificate->program);
        $this->assertSame('Naomi Aurora Teknologi', $certificate->location);
        $this->assertNull($certificate->institution);
        $this->assertNull($certificate->birth_date);
        $this->actingAs($user)->get('/workspaces/sertifikat-magang/'.$certificate->id)
            ->assertOk()->assertSee('Naomi Aurora Teknologi');
    }

    private function userWithServer(string $role = 'admin'): User
    {
        $server = Server::query()->create([
            'name' => 'Managed CHR',
            'host' => 'router.example.test',
            'port' => 443,
            'username' => 'rest-user',
            'password' => 'router-password',
            'ppp_profile' => 'sstp-profile',
            'ip_pool' => 'sstp-pool',
        ]);
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.test',
            'role' => $role,
            'password' => 'operator-test-password',
        ]);
        $user->servers()->attach($server);

        return $user;
    }
}
