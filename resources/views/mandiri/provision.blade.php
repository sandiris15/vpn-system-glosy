@extends('layouts.app', ['mode' => 'mandiri', 'activePage' => 'provision'])

@section('title', 'Provisioning mandiri')
@section('breadcrumb', 'Mode Mandiri / Konfigurasi')
@section('mobile-heading', 'Provisioning mandiri')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm text-slate-400">Router {{ $routerHost ?? 'terhubung' }}</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-white">Add Configuration</h1><p class="mt-2 text-sm text-slate-400">Buat akun SSTP dan siapkan script client.</p></div>
            <form method="POST" action="/mandiri/logout">@csrf<button type="submit" class="button-secondary">Akhiri sesi</button></form>
        </div>
        @if(isset($generatedScript) && $generatedScript)
            <section class="glass-panel mb-6 p-5 sm:p-7" x-data="{ copyState: 'idle', async copyScript() { try { await navigator.clipboard.writeText(document.getElementById('sstp-script').textContent); this.copyState = 'copied'; } catch (error) { this.copyState = 'error'; } } }">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div><span class="status-pill status-online"><span class="status-dot"></span>Provisioning selesai</span><h2 class="mt-3 text-xl font-semibold text-white">Script SSTP Client</h2><p class="mt-1 text-sm text-slate-400">Salin script dan jalankan hanya pada router tujuan yang Anda kelola.</p></div>
                    <button type="button" class="button-secondary shrink-0" @click="copyScript()"><span x-text="copyState === 'copied' ? 'Tersalin ✓' : (copyState === 'error' ? 'Gagal menyalin' : 'Salin script')">Salin script</span></button>
                </div>
                <pre id="sstp-script" class="mt-5 max-h-[420px] overflow-auto rounded-2xl border border-white/10 bg-slate-950/70 p-4 text-xs leading-6 text-cyan-100"><code>{{ $generatedScript }}</code></pre>
                <p x-cloak x-show="copyState === 'error'" class="mt-2 text-xs text-rose-200" role="alert">Clipboard tidak tersedia. Salin script secara manual dari kotak di atas.</p>
                <p class="mt-3 text-xs leading-5 text-amber-100/70">Script berisi password VPN. Jangan bagikan atau simpan pada lokasi publik.</p>
            </section>
        @endif
        <form method="POST" action="/mandiri/provision" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_290px]">
            @csrf
            <section class="glass-panel p-5 sm:p-7">
                <div class="mb-6"><h2 class="font-semibold text-white">Detail SSTP</h2><p class="mt-1 text-xs text-slate-400">Nilai ini dipakai untuk provisioning pada sesi router aktif.</p></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label for="vpn_username" class="field-label">Username VPN</label><input id="vpn_username" name="vpn_username" value="{{ old('vpn_username') }}" required autocomplete="off" class="input-glass" placeholder="cabang-01">@error('vpn_username')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div><label for="vpn_password" class="field-label">Password VPN</label><input id="vpn_password" name="vpn_password" type="password" required autocomplete="new-password" class="input-glass" placeholder="Password kuat">@error('vpn_password')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div><label for="local_address" class="field-label">Local address</label><input id="local_address" name="local_address" value="{{ old('local_address') }}" required class="input-glass" placeholder="Contoh: 10.10.0.1"></div>
                    <div><label for="remote_address" class="field-label">Remote address</label><input id="remote_address" name="remote_address" value="{{ old('remote_address') }}" required class="input-glass" placeholder="Contoh: 10.10.0.2"></div>
                    <div class="sm:col-span-2"><label for="sstp_port" class="field-label">Port SSTP server</label><input id="sstp_port" name="sstp_port" type="number" min="1" max="65535" value="{{ old('sstp_port', 443) }}" required class="input-glass"><p class="field-hint">Alamat dan port harus sesuai konfigurasi SSTP pada router ini.</p></div>
                </div>
                <div class="mt-6 rounded-2xl border border-white/10 bg-white/[.03] p-4">
                    <label class="flex items-start gap-3 text-sm text-slate-200"><input type="checkbox" name="add_default_route" value="1" @checked(old('add_default_route')) class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300 focus:ring-cyan-300/40">Tambahkan default route pada script client</label>
                    <p class="ml-7 mt-1 text-xs leading-5 text-slate-500">Aktifkan hanya jika seluruh trafik client memang harus melewati tunnel.</p>
                </div>
                <button type="submit" class="button-primary mt-6 w-full sm:w-auto">Provision &amp; generate script <span aria-hidden="true">→</span></button>
            </section>
            <aside class="space-y-4">
                <div class="glass-panel p-5">
                    <h2 class="text-sm font-semibold text-white">Sesi sementara</h2>
                    <p class="mt-2 text-xs leading-5 text-slate-400">Data router dan konfigurasi tidak masuk ke daftar monitoring. Akhiri sesi setelah selesai agar kredensial dihapus.</p>
                    <div class="mt-4 border-t border-white/10 pt-4 text-xs text-slate-500">Kredensial router: <span class="text-emerald-200">tidak ditampilkan</span></div>
                </div>
                <div class="glass-panel p-5">
                    <h2 class="text-sm font-semibold text-white">Periksa sebelum lanjut</h2>
                    <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-400"><li>• SSTP server dapat dijangkau dari jaringan client.</li><li>• Sertifikat TLS server valid dan dipercaya.</li><li>• Alamat IP tidak bentrok dengan konfigurasi lain.</li></ul>
                </div>
            </aside>
        </form>
    </div>
@endsection
