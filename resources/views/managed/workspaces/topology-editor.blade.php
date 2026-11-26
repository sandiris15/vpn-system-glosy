@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', $topology->name)
@section('breadcrumb', 'Workspaces / Topologi')
@section('mobile-heading', 'Topologi')

@section('content')
    <div x-data="topologyEditor(@js($topology->design), {{ $topology->revision }}, @js(route('workspaces.topology.update', $topology)), @js($canEdit))" x-init="initCanvas()">
        <a href="{{ route('workspaces.topology.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua topologi</a>
        <h1 class="mt-4 text-3xl font-semibold text-white">{{ $topology->name }}</h1>
        <p class="mt-2 text-sm text-amber-200">Gambar dapat dilihat dan diunduh semua pengguna yang login.</p>
        @unless($canEdit)
            <form method="POST" action="{{ route('workspaces.topology.unlock', $topology) }}" class="mt-5 flex max-w-xl flex-wrap items-end gap-3">
                @csrf
                <label class="min-w-52 flex-1"><span class="field-label">Password untuk mengedit</span><input name="password" type="password" class="input-glass" autocomplete="current-password" maxlength="128" required></label>
                <button type="submit" class="button-primary">Buka editor</button>
            </form>
        @endunless
        @if($canEdit)
            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" class="button-secondary" @click="add('rectangle')">+ Kotak</button>
                <button type="button" class="button-secondary" @click="add('ellipse')">+ Oval</button>
                <button type="button" class="button-secondary" @click="add('text')">+ Teks</button>
                <button type="button" class="button-secondary" @click="lineMode = !lineMode" x-text="lineMode ? 'Batal garis' : '+ Garis'"></button>
                <button type="button" class="button-secondary" @click="removeSelected()" :disabled="selected === null">Hapus pilihan</button>
                <button type="button" class="button-primary" @click="save()" :disabled="saving">Simpan perubahan</button>
            </div>
            <p class="mt-3 text-sm text-slate-400">Geser bentuk atau teks untuk memindahkan. Pilih Garis lalu tarik di kanvas. Klik objek untuk memilih.</p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <label class="min-w-48 flex-1 sm:max-w-xs"><span class="field-label">Teks objek terpilih</span><input class="input-glass" maxlength="80" :disabled="selected === null || items[selected]?.type === 'line'" :value="selected === null ? '' : (items[selected]?.text || '')" @input="setText($event.target.value)"></label>
                <label class="min-w-48"><span class="field-label">Muat contoh (mengganti gambar)</span><select class="input-glass" @change="loadExample($event.target.value); $event.target.value = ''"><option value="">Pilih contoh</option><option value="basic">Internet dan pelanggan</option><option value="dual">Dua ISP</option><option value="vpn">VPN antar kantor</option></select></label>
            </div>
        @endif
        <div class="mt-5 overflow-x-auto rounded-xl border border-white/20 bg-white">
            <canvas x-ref="canvas" width="1000" height="600" class="block w-full min-w-[600px] touch-none" aria-label="Kanvas topologi jaringan" @pointerdown="pointerDown($event)" @pointermove="pointerMove($event)" @pointerup="pointerUp($event)" @pointercancel="pointerUp($event)"></canvas>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" class="button-secondary" @click="download()">Unduh JPG</button>
            <p class="text-sm text-slate-300" role="status" x-text="message"></p>
        </div>
    </div>
@endsection
