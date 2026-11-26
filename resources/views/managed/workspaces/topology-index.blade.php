@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Topologi Jaringan')
@section('breadcrumb', 'Workspaces / Topologi')
@section('mobile-heading', 'Topologi')

@section('content')
    <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
    <h1 class="mt-4 text-3xl font-semibold text-white">Topologi jaringan</h1>
    <p class="mt-2 text-sm text-slate-400">Gambar dengan bentuk, teks, dan garis. Simpan di aplikasi atau unduh sebagai JPG.</p>
    <p class="mt-2 text-sm text-amber-200">Semua pengguna yang login dapat melihat dan mengunduh topologi. Password hanya melindungi perubahan, bukan kerahasiaan gambar.</p>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        <section class="glass-panel p-5">
            <h2 class="text-lg font-semibold">Buat topologi baru</h2>
            <p class="mt-2 text-sm text-slate-400">Tentukan password sebelum mulai menggambar. Simpan password dengan aman; password tidak dapat dipulihkan.</p>
            <form method="POST" action="{{ route('workspaces.topology.store') }}" class="mt-4 space-y-4">
                @csrf
                <label class="block"><span class="field-label">Nama topologi</span><input class="input-glass" name="name" value="{{ old('name') }}" maxlength="120" required></label>
                <label class="block"><span class="field-label">Password edit (minimal 8 karakter)</span><input class="input-glass" type="password" name="password" minlength="8" maxlength="128" autocomplete="new-password" required></label>
                <label class="block"><span class="field-label">Ulangi password</span><input class="input-glass" type="password" name="password_confirmation" minlength="8" maxlength="128" autocomplete="new-password" required></label>
                <button class="button-primary" type="submit">Buat dan mulai menggambar</button>
            </form>
        </section>
        <section class="glass-panel p-5">
            <h2 class="text-lg font-semibold">Contoh topologi sederhana</h2>
            <p class="mt-2 text-sm text-slate-400">Contoh dapat dimuat dan diubah setelah membuat topologi.</p>
            <ul class="mt-4 space-y-3 text-sm text-slate-200">
                <li class="rounded-xl bg-white/5 p-4">Internet → Router → Switch → Pelanggan</li>
                <li class="rounded-xl bg-white/5 p-4">ISP 1 + ISP 2 → Router → LAN</li>
                <li class="rounded-xl bg-white/5 p-4">Kantor pusat → VPN → Kantor cabang</li>
            </ul>
        </section>
    </div>
    <section class="mt-6 glass-panel p-5">
        <h2 class="text-lg font-semibold">Topologi tersimpan</h2>
        <div class="mt-4 space-y-3">
            @forelse($topologies as $topology)
                <a class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-white/10 p-4 hover:bg-white/5" href="{{ route('workspaces.topology.show', $topology) }}">
                    <span class="font-medium">{{ $topology->name }}</span>
                    <span class="text-xs text-slate-400">{{ $topology->user->name }} · {{ $topology->updated_at->format('d/m/Y H:i') }} · Lihat →</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">Belum ada topologi tersimpan.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $topologies->links() }}</div>
    </section>
@endsection
