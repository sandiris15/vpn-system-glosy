@extends('layouts.app', ['mode' => 'mandiri', 'activePage' => 'connection'])

@section('title', 'Koneksi router mandiri')
@section('breadcrumb', 'Mode Mandiri / Koneksi')
@section('mobile-heading', 'Koneksi router')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-7">
            <p class="text-sm text-slate-400">Provisioning mandiri</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-white">Hubungkan MikroTik</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Gunakan REST API RouterOS v7 melalui HTTPS. Kredensial hanya dipakai untuk sesi ini dan tidak disimpan di database aplikasi.</p>
        </div>
        @if($errors->any())<div class="mb-5 rounded-2xl border border-rose-300/20 bg-rose-300/10 p-4 text-sm text-rose-100" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="/mandiri/connection" class="glass-panel p-5 sm:p-8">
            @csrf
            <div class="mb-6 flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-cyan-300/10 text-cyan-200">⌁</span>
                <div><h2 class="font-semibold text-white">Detail koneksi HTTPS</h2><p class="mt-1 text-xs text-slate-400">Port REST API /www-ssl, bukan Binary API.</p></div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><label for="host" class="field-label">IP publik atau hostname</label><input id="host" name="host" type="text" inputmode="url" autocomplete="url" value="{{ old('host') }}" required class="input-glass" placeholder="vpn.example.id">@error('host')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="port" class="field-label">Port HTTPS</label><input id="port" name="port" type="number" min="1" max="65535" value="{{ old('port', 443) }}" required class="input-glass">@error('port')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="username" class="field-label">Username MikroTik</label><input id="username" name="username" autocomplete="username" value="{{ old('username') }}" required class="input-glass" placeholder="User dengan hak REST API">@error('username')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="sm:col-span-2"><label for="router_password" class="field-label">Password MikroTik</label><input id="router_password" name="router_password" type="password" autocomplete="new-password" required class="input-glass" placeholder="Tidak disimpan di browser atau database"><p class="field-hint">Jangan gunakan kredensial administrator utama jika user terbatas sudah mencukupi.</p>@error('router_password')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="mt-5 rounded-2xl border border-white/10 bg-white/[.03] p-4">
                <label class="flex items-start gap-3 text-sm text-slate-200">
                    <input type="checkbox" name="verify_tls" value="1" checked required class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300 focus:ring-cyan-300/40">
                    Verifikasi sertifikat TLS
                </label>
                <p class="ml-7 mt-1 text-xs leading-5 text-slate-500">Biarkan aktif untuk koneksi produksi. Sertifikat self-signed perlu dipasang dan dipercaya dengan benar di server aplikasi.</p>
            </div>
            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="/login" class="button-secondary">Kembali</a>
                <button type="submit" class="button-primary">Uji koneksi &amp; lanjutkan <span aria-hidden="true">→</span></button>
            </div>
        </form>
        <div class="mt-5 flex gap-3 rounded-2xl border border-cyan-200/10 bg-cyan-200/[.04] p-4">
            <span class="text-cyan-200" aria-hidden="true">i</span>
            <p class="text-xs leading-5 text-slate-400">Pastikan akses HTTPS www-ssl diizinkan hanya dari IP server aplikasi dan REST API diaktifkan pada RouterOS v7.</p>
        </div>
    </div>
@endsection
