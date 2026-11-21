@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'accounts'])

@section('title', 'Akun VPN')
@section('breadcrumb', 'Akun VPN')
@section('mobile-heading', 'Akun VPN')

@section('content')
    <div class="mb-7 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-400">{{ data_get($selectedServer, 'name', 'CHR utama') }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-white">Akun VPN</h1>
            <p class="mt-2 text-sm text-slate-400">
                @if($primaryOnly ?? true)
                    Kelola PPP Secret dan DST-NAT pada satu CHR utama.
                @else
                    Kelola akun SSTP dan port-forwarding yang direplikasi pada cluster.
                @endif
            </p>
            @if($primaryOnly ?? true)
                <p class="mt-3 inline-flex rounded-xl border border-amber-200/20 bg-amber-200/[.08] px-3 py-2 text-xs text-amber-100">
                    Mode satu CHR · perubahan tidak direplikasi ke backup.
                </p>
            @endif
        </div>
        <a href="/vpn-accounts/create" class="button-primary shrink-0">＋ <span>Tambah akun</span></a>
    </div>

    @if(is_array($latestManagedAccount ?? null))
        <section class="glass-panel mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-cyan-200">Akun terbaru dibuat oleh aplikasi</p>
                <p class="mt-2 font-semibold text-white">{{ data_get($latestManagedAccount, 'username', '—') }}</p>
                <p class="mt-1 font-mono text-xs text-slate-400">{{ data_get($latestManagedAccount, 'remote_address', '—') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="status-pill {{ data_get($latestManagedAccount, 'is_online') ? 'status-online' : 'status-offline' }}"><span class="status-dot"></span>{{ data_get($latestManagedAccount, 'is_online') ? 'Online' : 'Offline' }}</span>
                <a href="/vpn-accounts/{{ data_get($latestManagedAccount, 'id') }}" class="button-secondary">Lihat akun</a>
            </div>
        </section>
    @endif

    <section class="glass-panel mb-6 overflow-hidden">
        <div class="border-b border-white/10 px-5 py-5 sm:px-6">
            <h2 class="font-semibold text-white">PPP Secret</h2>
            <p class="mt-1 text-xs text-slate-400">Akun terbaru dari aplikasi ditampilkan lebih dulu. Gulir daftar untuk melihat Secret lainnya.</p>
        </div>
        <form method="GET" action="/vpn-accounts" class="flex flex-col gap-3 border-b border-white/10 p-4 sm:flex-row sm:items-center sm:p-5">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">Cari Secret berdasarkan username atau alamat IP</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" class="input-glass pl-10" placeholder="Cari Secret: username atau alamat IP">
            </label>
            <select name="status" class="input-glass sm:w-40" aria-label="Filter status">
                <option value="">Semua status</option>
                <option value="online" @selected(request('status') === 'online')>Online</option>
                <option value="offline" @selected(request('status') === 'offline')>Offline</option>
            </select>
            <button type="submit" class="button-secondary shrink-0">Cari Secret</button>
        </form>
        <div class="max-h-[34rem] overflow-auto">
            <table class="data-table min-w-[850px]">
                <thead><tr><th>Username</th><th>Remote address</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse(($accounts ?? []) as $account)
                        <tr>
                            <td><div class="font-medium text-white">{{ data_get($account, 'username', '—') }}</div><div class="mt-1 text-xs text-slate-500">Dibuat {{ data_get($account, 'created_at') ? \Illuminate\Support\Carbon::parse(data_get($account, 'created_at'))->format('d M Y') : '—' }}</div></td>
                            <td class="font-mono text-xs">{{ data_get($account, 'remote_address', '—') }}</td>
                            <td><span class="status-pill {{ data_get($account, 'is_online') ? 'status-online' : 'status-offline' }}"><span class="status-dot"></span>{{ data_get($account, 'is_online') ? 'Online' : 'Offline' }}</span></td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    @if(data_get($account, 'is_managed'))
                                        @php
                                            $copyPortText = collect(data_get($account, 'nat_rules', []))
                                                ->map(fn (array $nat): string => strtoupper((string) data_get($nat, 'protocol', 'tcp')).'/'.data_get($nat, 'public_port', '—').' -> '.data_get($selectedServer, 'public_ip', '—').':'.data_get($nat, 'public_port', '—').' ('.data_get($account, 'remote_address', '—').':'.data_get($nat, 'internal_port', '—').')')
                                                ->implode("\n");
                                        @endphp
                                        <div x-data="{ copyText: @js($copyPortText), copyState: 'idle', async copyPort() { try { await navigator.clipboard.writeText(this.copyText); this.copyState = 'copied'; } catch (error) { this.copyState = 'error'; } } }">
                                            <button type="button" class="button-table" @click="copyPort()" :disabled="!copyText"><span x-text="copyState === 'copied' ? 'Port tersalin ✓' : (copyState === 'error' ? 'Gagal' : 'Salin port')">Salin port</span></button>
                                        </div>
                                        <a href="/vpn-accounts/{{ data_get($account, 'id') }}" class="button-table">Detail / Script</a>
                                        @if($canModifyAccounts ?? true)
                                            <a href="/vpn-accounts/{{ data_get($account, 'id') }}/edit" class="button-table">Ubah</a>
                                            <form method="POST" action="/vpn-accounts/{{ data_get($account, 'id') }}" onsubmit="return confirm('Hapus akun VPN ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="button-table button-table-danger">Hapus</button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-500">Akun di luar aplikasi</span>
                                        @if(data_get($account, 'service') === 'sstp')
                                            <a href="/vpn-accounts/{{ data_get($account, 'id') }}" class="button-table">Generate script</a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-14 text-center"><span class="empty-icon">⌕</span><span class="mt-3 block font-medium text-slate-300">Tidak ada Secret ditemukan</span><span class="mt-1 block text-xs text-slate-500">Coba ubah pencarian atau buat akun VPN baru pada CHR ini.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($accounts) && is_object($accounts) && method_exists($accounts, 'links'))
            <div class="border-t border-white/10 px-5 py-4">{{ $accounts->withQueryString()->links() }}</div>
        @endif
    </section>

    @php
        $accountItems = is_object($accounts ?? null) && method_exists($accounts, 'items')
            ? $accounts->items()
            : ($accounts ?? []);
        $natEntries = collect($accountItems)->flatMap(function ($account) {
            return collect(data_get($account, 'nat_rules', []))->map(function ($nat) use ($account) {
                return ['account' => $account, 'nat' => $nat];
            });
        });
    @endphp
    <section class="glass-panel overflow-hidden" x-data="{ natSearchInput: '', natSearch: '' }">
        <div class="flex flex-col gap-4 border-b border-white/10 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 class="font-semibold text-white">DST-NAT</h2>
                <p class="mt-1 text-xs text-slate-400">Aturan port-forwarding untuk akun VPN pada halaman ini.</p>
            </div>
            <form class="flex w-full gap-2 sm:max-w-lg" @submit.prevent="natSearch = natSearchInput">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Cari aturan DST-NAT</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    <input type="search" x-model="natSearchInput" class="input-glass pl-10" placeholder="Cari username, IP, protokol, atau port">
                </label>
                <button type="submit" class="button-secondary shrink-0">Cari</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table min-w-[720px]">
                <thead><tr><th>Username</th><th>Protokol / port publik</th><th>Tujuan internal</th></tr></thead>
                <tbody>
                    @forelse($natEntries as $entry)
                        @php($account = $entry['account'])
                        @php($nat = $entry['nat'])
                        @php($copyText = strtoupper((string) data_get($nat, 'protocol', 'tcp')) . '/' . data_get($nat, 'public_port', '—') . ' -> ' . data_get($account, 'remote_address', '—') . ':' . data_get($nat, 'internal_port', '—'))
                        <tr data-search="{{ \Illuminate\Support\Str::lower(implode(' ', [(string) data_get($account, 'username', ''), (string) data_get($account, 'remote_address', ''), (string) data_get($nat, 'protocol', 'tcp'), (string) data_get($nat, 'public_port', ''), (string) data_get($nat, 'internal_port', '')])) }}" x-show="!natSearch || $el.dataset.search.includes(natSearch.trim().toLowerCase())">
                            <td class="font-medium text-white">{{ data_get($account, 'username', '—') }}</td>
                            <td>
                                <div class="flex items-center gap-2" x-data="{ copyText: @js($copyText), copyState: 'idle', async copyRule() { try { await navigator.clipboard.writeText(this.copyText); this.copyState = 'copied'; } catch (error) { this.copyState = 'error'; } } }">
                                    <span class="inline-flex rounded-lg bg-white/[.06] px-2 py-1 font-mono text-xs text-slate-300">{{ data_get($nat, 'protocol', 'tcp') }}/{{ data_get($nat, 'public_port', '—') }}</span>
                                    <button type="button" class="button-table" aria-label="Salin ringkasan DST-NAT" @click="copyRule()"><span x-text="copyState === 'copied' ? 'Tersalin ✓' : (copyState === 'error' ? 'Gagal' : 'Salin')">Salin</span></button>
                                    <span x-cloak x-show="copyState === 'error'" class="text-xs text-rose-200" role="alert">Clipboard gagal; salin manual.</span>
                                </div>
                            </td>
                            <td class="font-mono text-xs">{{ data_get($account, 'remote_address', '—') }}:{{ data_get($nat, 'internal_port', '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-12 text-center"><span class="empty-icon">⌕</span><span class="mt-3 block font-medium text-slate-300">Belum ada aturan DST-NAT</span><span class="mt-1 block text-xs text-slate-500">Aturan port-forwarding akun akan muncul di sini.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <p class="mt-4 text-xs text-slate-500">Status Online/Offline harus bersumber dari PPP Active MikroTik, bukan dari keberadaan PPP Secret.</p>
@endsection
