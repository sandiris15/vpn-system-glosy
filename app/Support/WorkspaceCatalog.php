<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class WorkspaceCatalog
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function visibleTo(User $user): Collection
    {
        return collect([
            [
                'title' => 'Sertifikat PKL/MAGANG',
                'description' => 'Buat dan unduh sertifikat magang bernomor otomatis, dengan riwayat tersimpan.',
                'status' => 'Aktif',
                'route' => 'workspaces.internship-certificates.index',
                'icon' => '▣',
                'active' => true,
            ],
            [
                'title' => 'PPP Secret & Speedtest Script',
                'description' => 'Lihat status akun PPP, tambah Secret, kelola DST-NAT, dan preview script SSTP.',
                'status' => 'Aktif',
                'route' => 'vpn-accounts.index',
                'icon' => '◉',
                'active' => true,
            ],
            [
                'title' => 'Template Hotspot',
                'description' => 'Preview halaman login Hotspot dengan pilihan login, logo, dan perpaduan warna.',
                'status' => 'Aktif',
                'route' => 'workspaces.hotspot-template',
                'icon' => '▧',
                'active' => true,
                'recent_rank' => 6,
            ],
            [
                'title' => 'Forum Diskusi',
                'description' => 'Bagikan topik operasional dan balasan dengan pengguna aplikasi yang sudah login.',
                'status' => 'Aktif',
                'route' => 'forum.index',
                'icon' => '☷',
                'active' => true,
            ],
            [
                'title' => 'Topologi, Interface & VLAN',
                'description' => 'Gambar topologi jaringan sederhana, simpan dan unduh sebagai JPG.',
                'status' => 'Aktif',
                'route' => 'workspaces.topology.index',
                'icon' => '⇄',
                'active' => true,
                'recent_rank' => 7,
            ],
            [
                'title' => 'Auto Failover Route ISP',
                'description' => 'Buat script RouterOS v7 agar jalur ISP 2 otomatis mengambil alih saat ISP 1 terputus.',
                'status' => 'Aktif',
                'route' => 'workspaces.isp-failover',
                'icon' => '↗',
                'active' => true,
                'recent_rank' => 1,
            ],
            [
                'title' => 'Auto ON/OFF Ethernet',
                'description' => 'Jadwalkan interface Ethernet hidup dan mati setiap hari atau sekali pada tanggal tertentu.',
                'status' => 'Aktif',
                'route' => 'workspaces.ethernet-scheduler',
                'icon' => '⏱',
                'active' => true,
                'recent_rank' => 2,
            ],
            [
                'title' => 'Auto ON/OFF VLAN',
                'description' => 'Jadwalkan interface VLAN hidup dan mati setiap hari atau sekali pada tanggal tertentu.',
                'status' => 'Aktif',
                'route' => 'workspaces.vlan-scheduler',
                'icon' => '⏱',
                'active' => true,
                'recent_rank' => 3,
            ],
            [
                'title' => 'Kumpulan Script Umum',
                'description' => 'Pilih script PPP Secret yang sering digunakan dan isi input sesuai kebutuhan script.',
                'status' => 'Aktif',
                'route' => 'workspaces.script-library',
                'icon' => '⌘',
                'active' => true,
                'admin_only' => true,
                'recent_rank' => 4,
            ],
            [
                'title' => 'Setup MikroTik Baru',
                'description' => 'Buat preview script DHCP WAN, LAN/VLAN, NAT, PPPoE, Hotspot, DNS, dan keamanan layanan.',
                'status' => 'Aktif',
                'route' => 'workspaces.mikrotik-setup',
                'icon' => '⌘',
                'active' => true,
                'admin_only' => true,
                'recent_rank' => 5,
            ],
        ])->filter(fn (array $workspace): bool => ! ($workspace['admin_only'] ?? false) || ! $user->isOperator())
            ->values();
    }
}
