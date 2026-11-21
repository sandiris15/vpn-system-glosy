@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'dashboard'])

@section('title', 'Dashboard VPN')
@section('breadcrumb', 'Dashboard')
@section('mobile-heading', 'Dashboard VPN')

@section('content')
    @php
        $statCards = [
            ['label' => 'Akun VPN', 'key' => 'total_vpn', 'note' => 'PPP Secret pada CHR'],
            ['label' => 'Sesi aktif', 'key' => 'active_vpn', 'note' => 'Terhubung saat ini'],
            ['label' => 'Sesi offline', 'key' => 'offline_vpn', 'note' => 'Belum terhubung'],
        ];
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[.18em] text-rose-200/80">Status VPN saat ini</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">{{ data_get($selectedServer, 'name', 'CHR utama') }}</h1>
                <p class="mt-2 text-sm text-slate-400">
                    Pemeriksaan terakhir:
                    {{ data_get($selectedServer, 'last_checked_at') ? \Illuminate\Support\Carbon::parse(data_get($selectedServer, 'last_checked_at'))->diffForHumans() : 'baru saja' }}
                </p>
            </div>
            <a href="{{ route('workspaces.index') }}" class="button-primary shrink-0">Buka Workspaces <span aria-hidden="true">→</span></a>
        </section>

        <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan VPN saat ini">
            @foreach($statCards as $card)
                <article class="glass-card p-5">
                    <p class="text-sm text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-4 text-3xl font-semibold tracking-tight text-white">{{ data_get($stats ?? [], $card['key'], '—') }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ $card['note'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="glass-panel overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-white/10 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="font-semibold text-white">Status akun VPN terbaru</h2>
                    <p class="mt-1 text-xs text-slate-400">Sesi dan remote address dari PPP Secret pada CHR utama.</p>
                </div>
                <a href="{{ route('vpn-accounts.index') }}" class="text-sm font-medium text-rose-200 hover:text-white">Semua akun <span aria-hidden="true">→</span></a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[560px]">
                    <thead><tr><th>Username</th><th>Remote address</th><th>Status</th><th>Uptime</th></tr></thead>
                    <tbody>
                        @forelse(($recentAccounts ?? []) as $account)
                            <tr>
                                <td class="font-medium text-white">{{ data_get($account, 'username', '—') }}</td>
                                <td class="font-mono text-xs">{{ data_get($account, 'remote_address', '—') }}</td>
                                <td><span class="status-pill {{ data_get($account, 'is_online') ? 'status-online' : 'status-offline' }}"><span class="status-dot"></span>{{ data_get($account, 'is_online') ? 'Online' : 'Offline' }}</span></td>
                                <td>{{ data_get($account, 'uptime', '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-12 text-center"><span class="empty-icon">◉</span><span class="mt-3 block font-medium text-slate-300">Belum ada akun VPN</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2" aria-label="Notifikasi dan diskusi">
            <article class="glass-panel overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-white/10 px-5 py-5 sm:px-6">
                    <div><h2 class="font-semibold text-white">Notifikasi aktivitas</h2><p class="mt-1 text-xs text-slate-400">Login dan perubahan terbaru.</p></div>
                    <span class="preview-pill preview-pill-muted">Audit</span>
                </div>
                <div class="divide-y divide-white/[.07]">
                    @forelse(($activityLogs ?? []) as $activity)
                        <article class="px-5 py-4 sm:px-6">
                            <p class="text-sm font-medium text-white">{{ data_get($activity, 'action', 'Aktivitas') }}</p>
                            <p class="mt-1 break-words text-xs leading-5 text-slate-400">{{ data_get($activity, 'description', '—') }}</p>
                            <p class="mt-2 text-[11px] text-slate-500">{{ data_get($activity, 'actor', 'Sistem') }} · {{ data_get($activity, 'created_at') ? \Illuminate\Support\Carbon::parse(data_get($activity, 'created_at'))->diffForHumans() : '—' }}</p>
                        </article>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-400">Belum ada notifikasi aktivitas.</p>
                    @endforelse
                </div>
            </article>

            <article class="glass-panel overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-white/10 px-5 py-5 sm:px-6">
                    <div><h2 class="font-semibold text-white">Diskusi terbaru</h2><p class="mt-1 text-xs text-slate-400">Topik dari pengguna aplikasi.</p></div>
                    <a href="{{ route('forum.index') }}" class="text-sm font-medium text-rose-200 hover:text-white">Forum <span aria-hidden="true">→</span></a>
                </div>
                <div class="divide-y divide-white/[.07]">
                    @forelse(($forumTopics ?? []) as $topic)
                        <a href="{{ route('forum.show', $topic) }}" class="block px-5 py-4 hover:bg-white/[.025] sm:px-6">
                            <p class="truncate text-sm font-medium text-white">{{ $topic->title }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $topic->user?->name ?? 'Pengguna dihapus' }} · {{ $topic->replies_count }} balasan · {{ $topic->created_at->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-400">Belum ada diskusi.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
