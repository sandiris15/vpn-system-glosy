@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'create'])

@section('title', 'Buat akun VPN')
@section('breadcrumb', 'Akun VPN / Konfigurasi baru')
@section('mobile-heading', 'Konfigurasi baru')

@section('content')
    <div class="mb-7">
        <a href="/vpn-accounts" class="text-sm text-slate-400 hover:text-white">← Kembali ke akun VPN</a>
        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Konfigurasi baru</h1>
        <p class="mt-2 text-sm text-slate-400">
            @if($primaryOnly ?? true)
                Buat akun SSTP pada CHR utama dengan alamat tunnel statis.
            @else
                Buat akun SSTP yang direplikasi ke seluruh node cluster.
            @endif
        </p>
    </div>

    <form method="POST" action="/vpn-accounts" x-data="{ protocol: @js(old('protocol', '')), internalPort: @js(old('internal_port', '')) }" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        <div class="space-y-6">
            @error('provisioning')
                <div class="rounded-2xl border border-rose-200/20 bg-rose-200/[.08] p-4 text-sm leading-6 text-rose-100" role="alert">{{ $message }}</div>
            @enderror
            @if($primaryOnly ?? true)
                <div class="rounded-2xl border border-amber-200/20 bg-amber-200/[.08] p-4 text-sm leading-6 text-amber-100" role="status">
                    Mode satu CHR: Secret, alamat IP statis, dan DST-NAT hanya dikelola pada {{ data_get($selectedServer ?? null, 'name', 'CHR utama') }}.
                </div>
            @endif
            <section class="glass-panel p-5 sm:p-7">
                <div class="mb-6 flex items-center gap-3">
                    <span class="step-number">01</span><div><h2 class="font-semibold text-white">Akun SSTP</h2><p class="mt-1 text-xs text-slate-400">Informasi dasar untuk koneksi VPN.</p></div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><span class="field-label">{{ ($primaryOnly ?? true) ? 'CHR VPN' : 'Cluster SSTP' }}</span><div class="input-glass flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-cyan-200"></span>{{ data_get($selectedServer ?? null, 'name', 'Server utama') }}{{ ($primaryOnly ?? true) ? '' : ' + '.max(0, ($clusterServerCount ?? 1) - 1).' node cadangan' }}</div><p class="field-hint">{{ ($primaryOnly ?? true) ? 'Akun hanya dibuat pada CHR utama; failover ke node cadangan belum diaktifkan.' : 'Akun dan IP tunnel yang sama dibuat pada setiap node agar client bisa failover.' }}</p></div>
                    <div><label for="username" class="field-label">Username VPN</label><input id="username" name="username" value="{{ old('username') }}" required maxlength="64" autocomplete="off" class="input-glass" placeholder="contoh: cabang-jakarta">@error('username')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div><label for="password" class="field-label">Password VPN</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="input-glass" placeholder="Buat password kuat">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div><label for="local_address" class="field-label">Local address (statis)</label><input id="local_address" value="{{ config('managed.vpn_local_address') }}" class="input-glass" readonly aria-readonly="true"><p class="field-hint">Satu alamat local tetap dipakai oleh semua akun SSTP.</p></div>
                    <div><label for="remote_address" class="field-label">Remote address</label><input id="remote_address" value="Otomatis dipilih dan disimpan pada Secret" class="input-glass" readonly aria-readonly="true"><p class="field-hint">Setiap akun mendapat IP statis yang tidak berubah saat reconnect.</p></div>
                </div>
            </section>

            <section class="glass-panel p-5 sm:p-7">
                <div class="mb-6 flex items-center gap-3">
                    <span class="step-number">02</span><div><h2 class="font-semibold text-white">DST-NAT (opsional)</h2><p class="mt-1 text-xs text-slate-400">Arahkan port publik ke layanan pada perangkat VPN.</p></div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="protocol" class="field-label">Pilihan DST-NAT</label><select id="protocol" name="protocol" x-model="protocol" class="input-glass"><option value="">Tanpa DST-NAT</option><option value="tcp">TCP</option><option value="udp">UDP</option></select>@error('protocol')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div x-show="protocol !== ''" x-cloak><label for="public_port" class="field-label">Public port</label><input id="public_port" value="Otomatis dialokasikan" class="input-glass" readonly aria-readonly="true"><p class="field-hint">Port publik dipilih otomatis setelah pengecekan konflik.</p></div>
                    <div x-show="protocol !== ''" x-cloak><label for="internal_port" class="field-label">Port internal</label><select id="internal_port" name="internal_port" x-model="internalPort" :disabled="protocol === ''" class="input-glass"><option value="">Pilih layanan</option><option value="8291">Winbox · 8291</option><option value="8728">MikroTik API · 8728</option><option value="80">WebFig · 80</option><option value="custom">Manual</option></select>@error('internal_port')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div x-show="protocol !== '' && internalPort === 'custom'" x-cloak><label for="custom_internal_port" class="field-label">Port internal manual</label><input id="custom_internal_port" name="custom_internal_port" type="number" min="1" max="65535" value="{{ old('custom_internal_port') }}" :disabled="protocol === '' || internalPort !== 'custom'" class="input-glass" placeholder="Contoh: 8080">@error('custom_internal_port')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div x-show="protocol !== '' && internalPort === 'custom'" x-cloak><label for="manual_comment" class="field-label">Comment DST-NAT manual</label><input id="manual_comment" name="manual_comment" value="{{ old('manual_comment') }}" maxlength="191" :disabled="protocol === '' || internalPort !== 'custom'" class="input-glass" placeholder="Contoh: username-aplikasi">@error('manual_comment')<p class="field-error">{{ $message }}</p>@enderror</div>
                </div>
                <p class="mt-4 rounded-xl border border-amber-200/10 bg-amber-200/[.05] p-3 text-xs leading-5 text-amber-100/80">Public IP dan rentang port harus ditentukan administrator. Comment otomatis mengikuti username dan layanan yang dipilih.</p>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="glass-panel p-5">
                <h2 class="font-semibold text-white">Sebelum membuat akun</h2>
                <ul class="mt-4 space-y-3 text-xs leading-5 text-slate-400">
                    @if($primaryOnly ?? true)
                        <li class="flex gap-2"><span class="text-cyan-200">01</span><span>Koneksi dan hak akses CHR utama akan diverifikasi.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">02</span><span>Remote address statis dipilih dari pool yang tersedia.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">03</span><span>Public port otomatis dan konflik diperiksa pada CHR utama.</span></li>
                    @else
                        <li class="flex gap-2"><span class="text-cyan-200">01</span><span>Koneksi dan hak akses seluruh node akan diverifikasi.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">02</span><span>IP tunnel dan public port yang sama diperiksa pada setiap node.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">03</span><span>Provisioning di-rollback jika satu node gagal.</span></li>
                    @endif
                </ul>
                <div class="mt-5 border-t border-white/10 pt-5">
                    <label class="flex items-start gap-3 text-xs leading-5 text-slate-300">
                        <input type="checkbox" name="confirm" value="1" required class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300 focus:ring-cyan-300/40">
                        Saya berwenang membuat konfigurasi pada server dan jaringan tujuan.
                    </label>
                </div>
                <button type="submit" class="button-primary mt-5 w-full">Buat akun <span aria-hidden="true">→</span></button>
                <a href="/vpn-accounts" class="button-secondary mt-2 w-full">Batal</a>
            </section>
            <p class="px-2 text-center text-[11px] leading-5 text-slate-500">Jangan membagikan password VPN. Password tidak ditampilkan kembali pada halaman ini.</p>
        </aside>
    </form>
@endsection
