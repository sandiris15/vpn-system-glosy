@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'dashboard'])

@section('title', 'CHR tidak tersedia')
@section('breadcrumb', 'Dashboard')
@section('mobile-heading', 'Dashboard')

@section('content')
    <section class="glass-panel mx-auto max-w-3xl p-6 sm:p-8">
        <span class="status-pill status-offline"><span class="status-dot"></span>CHR tidak tersedia</span>
        <h1 class="mt-4 text-2xl font-semibold text-white">Tidak dapat membaca data router</h1>
        <p class="mt-2 text-sm leading-6 text-slate-400">
            Koneksi aman ke node utama {{ data_get($selectedServer, 'name', 'cluster SSTP') }} gagal.
            Periksa koneksi REST API dan sertifikat TLS, lalu coba lagi.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="/dashboard" class="button-secondary">Coba lagi</a>
            <form method="POST" action="/logout">@csrf<button type="submit" class="button-secondary">Keluar</button></form>
        </div>
    </section>
@endsection
