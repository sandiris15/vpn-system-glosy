@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Workspaces')
@section('breadcrumb', 'Workspaces')
@section('mobile-heading', 'Workspaces')

@section('content')
    <div class="mb-7">
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-rose-200/80">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">Pilih workspace</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Setiap workspace membuka tool untuk satu jenis pekerjaan. Fitur berstatus rancangan belum menjalankan perubahan pada router.</p>
    </div>

    @php
        $workspaces = [
            [
                'title' => 'PPP Secret & Speedtest Script',
                'description' => 'Lihat status akun PPP, tambah Secret, kelola DST-NAT, dan preview script SSTP.',
                'status' => 'Aktif',
                'url' => route('vpn-accounts.index'),
                'icon' => '◉',
                'active' => true,
            ],
            [
                'title' => 'Template Hotspot',
                'description' => 'Lihat rancangan halaman login Hotspot sebelum tool kustomisasi dan export tersedia.',
                'status' => 'Preview',
                'url' => route('workspaces.hotspot-template'),
                'icon' => '▧',
                'active' => true,
            ],
            [
                'title' => 'Forum Diskusi',
                'description' => 'Bagikan topik operasional dan balasan dengan pengguna aplikasi yang sudah login.',
                'status' => 'Aktif',
                'url' => route('forum.index'),
                'icon' => '☷',
                'active' => true,
            ],
            [
                'title' => 'Topologi, Interface & VLAN',
                'description' => 'Gambaran jalur jaringan, pemetaan port, kontrol interface, dan konfigurasi VLAN.',
                'status' => 'Rancangan',
                'url' => route('workspaces.planned', 'network-automation'),
                'icon' => '⇄',
                'active' => false,
            ],
            [
                'title' => 'Auto Speedtest & Failover Multi-ISP',
                'description' => 'Target pengujian ISP, health-check, dan prioritas jalur cadangan.',
                'status' => 'Rancangan',
                'url' => route('workspaces.planned', 'isp-failover'),
                'icon' => '↗',
                'active' => false,
            ],
            [
                'title' => 'Setup MikroTik & Port Publik',
                'description' => 'Wizard identitas, WAN, VPN, NAT, review, dan export konfigurasi.',
                'status' => 'Rancangan',
                'url' => route('workspaces.planned', 'router-setup'),
                'icon' => '⌘',
                'active' => false,
            ],
        ];
    @endphp

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($workspaces as $workspace)
            <a href="{{ $workspace['url'] }}" class="glass-panel group flex min-h-56 flex-col p-5 transition hover:-translate-y-0.5 hover:border-rose-200/25">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-rose-300/10 text-lg text-rose-100">{{ $workspace['icon'] }}</span>
                    <span class="preview-pill {{ $workspace['active'] ? '' : 'preview-pill-muted' }}">{{ $workspace['status'] }}</span>
                </div>
                <h2 class="mt-5 text-lg font-semibold text-white">{{ $workspace['title'] }}</h2>
                <p class="mt-2 flex-1 text-sm leading-6 text-slate-400">{{ $workspace['description'] }}</p>
                <span class="mt-5 text-sm font-medium {{ $workspace['active'] ? 'text-rose-200' : 'text-slate-400' }}">
                    {{ $workspace['active'] ? 'Buka workspace' : 'Lihat rancangan' }} <span aria-hidden="true">→</span>
                </span>
            </a>
        @endforeach
    </div>
@endsection
