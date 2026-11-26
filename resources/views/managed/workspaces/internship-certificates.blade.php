@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Sertifikat PKL/MAGANG')
@section('breadcrumb', 'Workspaces / Sertifikat PKL/MAGANG')
@section('mobile-heading', 'Sertifikat PKL/MAGANG')

@section('content')
    <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
    <h1 class="mt-4 text-3xl font-semibold text-white">Sertifikat PKL/MAGANG</h1>
    <p class="mt-2 text-sm text-slate-400">Buat sertifikat, simpan riwayat, dan unduh sebagai gambar PNG.</p>
    <p class="mt-2 text-sm text-amber-200">Semua pengguna yang login dapat membuat dan mengunduh sertifikat dengan tanda tangan direktur, serta melihat riwayatnya.</p>

    <div class="mt-6 grid gap-5 lg:grid-cols-2" x-data="internshipCertificatePreview(@js([
        'logo' => route('workspaces.internship-certificates.asset', 'logo'),
        'signature' => route('workspaces.internship-certificates.asset', 'signature'),
    ]))">
        <section class="glass-panel p-5">
            <h2 class="text-lg font-semibold">Buat sertifikat</h2>
            <p class="mt-2 text-sm text-slate-400">Nomor otomatis dimulai dari 022. Bulan dan tahun mengikuti tanggal terbit.</p>
            <form x-ref="form" @input="update()" @change="update()" method="POST" action="{{ route('workspaces.internship-certificates.store') }}" class="mt-4 space-y-4">
                @csrf
                <label class="block"><span class="field-label">Nama peserta</span><input class="input-glass" name="name" value="{{ old('name') }}" maxlength="120" required></label>
                <label class="block"><span class="field-label">Asal instansi/sekolah</span><input class="input-glass" name="institution" value="{{ old('institution') }}" maxlength="120" required></label>
                <label class="block"><span class="field-label">Tanggal lahir</span><input class="input-glass" type="date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->format('Y-m-d') }}" required></label>
                <label class="block"><span class="field-label">Jenis kegiatan</span><select class="input-glass" name="program" required><option value="magang" @selected(old('program', 'magang') === 'magang')>MAGANG</option><option value="pkl" @selected(old('program') === 'pkl')>PKL</option></select></label>
                <label class="block"><span class="field-label">Lokasi kegiatan</span><input class="input-glass" name="location" value="{{ old('location', 'Naomi Aurora Teknologi') }}" maxlength="120" required></label>
                <label class="block"><span class="field-label">Tanggal mulai kegiatan</span><input class="input-glass" type="date" name="start_date" value="{{ old('start_date') }}" required></label>
                <label class="block"><span class="field-label">Tanggal selesai kegiatan</span><input class="input-glass" type="date" name="end_date" value="{{ old('end_date') }}" required></label>
                <label class="block"><span class="field-label">Tanggal terbit</span><input class="input-glass" type="date" name="issued_date" value="{{ old('issued_date', now()->format('Y-m-d')) }}" required></label>
                <button class="button-primary" type="submit">Buat sertifikat</button>
            </form>
        </section>
        <section class="glass-panel p-5">
            <h2 class="text-lg font-semibold">Preview langsung</h2>
            <p class="mt-2 text-sm text-slate-400">Preview diperbarui saat data diisi. Nomor resmi ditetapkan setelah sertifikat dibuat.</p>
            <p x-cloak x-show="error" x-text="error" class="mt-3 text-sm text-rose-200" role="alert"></p>
            <canvas x-ref="canvas" width="1800" height="1200" class="mt-4 h-auto w-full rounded-lg bg-white" aria-label="Preview sertifikat"></canvas>
        </section>
    </div>
    <section class="glass-panel mt-6 p-5">
            <h2 class="text-lg font-semibold">Riwayat sertifikat</h2>
            <div class="mt-4 space-y-3">
                @forelse($certificates as $certificate)
                    <a class="block rounded-xl border border-white/10 p-4 hover:bg-white/5" href="{{ route('workspaces.internship-certificates.show', $certificate) }}">
                        <span class="block break-words font-medium">{{ $certificate->name }}</span>
                        <span class="mt-1 block text-xs text-slate-400">{{ strtoupper($certificate->program) }} · {{ $certificate->number }} · {{ $certificate->issued_date->format('d/m/Y') }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">Belum ada sertifikat tersimpan.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $certificates->links() }}</div>
    </section>
@endsection
