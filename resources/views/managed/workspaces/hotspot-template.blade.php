@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Template Hotspot')
@section('breadcrumb', 'Workspaces / Template Hotspot')
@section('mobile-heading', 'Template Hotspot')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Template halaman login Hotspot</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Pilih rancangan untuk melihat gaya halaman. Kustomisasi aset dan export belum tersedia.</p>
        </div>
        <span class="preview-pill preview-pill-muted">Preview saja</span>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach([
            ['name' => 'Clean Connect', 'tone' => 'hotspot-clean', 'tagline' => 'Internet cepat, selalu dekat'],
            ['name' => 'Promo Harian', 'tone' => 'hotspot-promo', 'tagline' => 'Pilih paket yang cocok'],
            ['name' => 'Modern WiFi', 'tone' => 'hotspot-modern', 'tagline' => 'Terhubung dalam satu klik'],
        ] as $template)
            <article class="glass-panel p-4">
                <div class="hotspot-template">
                    <div class="hotspot-mock {{ $template['tone'] }}">
                        <div class="hotspot-mock-brand">LOGO USAHA</div>
                        <p class="mt-3 text-sm font-semibold text-white">{{ $template['tagline'] }}</p>
                        <div class="mt-3 rounded-lg bg-slate-950/30 p-2 text-[10px] text-white/80">Username pelanggan</div>
                        <div class="mt-2 rounded-lg bg-white px-2 py-1.5 text-center text-[10px] font-semibold text-slate-900">MASUK HOTSPOT</div>
                        <div class="mt-3 flex justify-between text-[9px] text-white/70"><span>Paket: Rp —</span><span>CS: —</span></div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-medium text-white">{{ $template['name'] }}</h2>
                    <span class="text-xs text-slate-500">Preview</span>
                </div>
            </article>
        @endforeach
    </div>
@endsection
