@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'accounts'])

@section('title', 'Ubah akun VPN')
@section('breadcrumb', 'Akun VPN / Ubah')
@section('mobile-heading', 'Ubah akun VPN')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="/vpn-accounts" class="text-sm text-slate-400 hover:text-white">← Kembali ke akun VPN</a>
        <section class="glass-panel mt-5 p-5 sm:p-8">
            <h1 class="text-2xl font-semibold tracking-tight text-white">Ubah password VPN</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                Username <span class="font-medium text-white">{{ data_get($account, 'username') }}</span>
                pada CHR {{ data_get($selectedServer, 'name') }}.
            </p>
            <form method="POST" action="/vpn-accounts/{{ data_get($account, 'id') }}" class="mt-7 space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <label for="password" class="field-label">Password baru</label>
                    <input id="password" name="password" type="password" required minlength="8" maxlength="255" autocomplete="new-password" class="input-glass">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="field-label">Konfirmasi password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" maxlength="255" autocomplete="new-password" class="input-glass">
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="submit" class="button-primary">Simpan password</button>
                    <a href="/vpn-accounts" class="button-secondary">Batal</a>
                </div>
            </form>
        </section>
    </div>
@endsection
