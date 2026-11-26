<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk · RUANG NOC NAT WAIPI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center px-4 py-10 text-slate-100">
    <div class="wallpaper fixed inset-0 -z-10" aria-hidden="true"></div>
    <div class="fixed inset-0 -z-10 bg-slate-950/55" aria-hidden="true"></div>
    <main class="w-full max-w-[460px]">
        <a href="/" class="mb-8 flex flex-col items-center gap-2" aria-label="Nat Waipi - Ruang Network Office Center">
            <img src="/images/nat-waipi.png" alt="Nat Waipi" class="h-auto w-56 max-w-full">
            <span class="text-xs font-semibold tracking-wide text-slate-300">RUANG NETWORK OFFICE CENTER</span>
        </a>
        <section class="glass-panel p-6 sm:p-8">
            <div class="mb-7">
                <p class="text-xs font-semibold uppercase tracking-[.2em] text-rose-200">Managed access</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">Selamat datang kembali</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">Masuk untuk mengelola cluster SSTP dan seluruh server cadangannya.</p>
            </div>
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-rose-300/20 bg-rose-300/10 p-3 text-sm text-rose-100" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="/login" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="field-label">Username atau email</label>
                    <input id="email" name="email" type="text" autocomplete="username" value="{{ old('email') }}" required autofocus class="input-glass" placeholder="operator / nama@perusahaan.id">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="field-label">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="input-glass" placeholder="Masukkan password">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300 focus:ring-cyan-300/40">
                    Ingat sesi saya
                </label>
                <button type="submit" class="button-primary w-full">Masuk ke dashboard <span aria-hidden="true">→</span></button>
            </form>
            <div class="my-6 flex items-center gap-3"><span class="h-px flex-1 bg-white/10"></span><span class="text-[10px] font-medium uppercase tracking-[.15em] text-slate-500">atau</span><span class="h-px flex-1 bg-white/10"></span></div>
            <a href="/mandiri/connection" class="button-secondary w-full">Lanjutkan dengan router mandiri</a>
            <p class="mt-6 text-center text-xs leading-5 text-slate-500">Akses hanya untuk pengguna berwenang. Aktivitas operasional dapat dicatat untuk audit.</p>
        </section>
        <p class="mt-6 text-center text-xs text-slate-500">© {{ date('Y') }} RUANG NOC NAT WAIPI · Network Operations</p>
    </main>
</body>
</html>
