@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Workspaces')
@section('breadcrumb', 'Workspaces')
@section('mobile-heading', 'Workspaces')

@section('content')
    <div x-data="{ query: '', appliedQuery: '' }">
        <div class="mb-7">
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-rose-200/80">Ruang kerja</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">Pilih workspace</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Setiap workspace membuka tool untuk satu jenis pekerjaan. Fitur berstatus rancangan belum menjalankan perubahan pada router.</p>
            <form class="mt-5 flex max-w-xl gap-3" role="search" @submit.prevent="appliedQuery = query.trim().toLowerCase()">
                <label for="workspace-search" class="sr-only">Cari workspace</label>
                <input id="workspace-search" x-model="query" type="search" class="input-glass" placeholder="Cari workspace..." autocomplete="off">
                <button type="submit" class="button-primary shrink-0">Cari</button>
                <button type="button" class="button-secondary shrink-0" x-show="query || appliedQuery" x-cloak @click="query = ''; appliedQuery = ''">Hapus</button>
            </form>
        </div>

        <div x-ref="workspaceCards" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($workspaces as $workspace)
                <a href="{{ route($workspace['route']) }}" data-search="{{ mb_strtolower($workspace['title'].' '.$workspace['description']) }}" x-show="!appliedQuery || $el.dataset.search.includes(appliedQuery)" x-cloak class="glass-panel group flex min-h-56 flex-col p-5 transition hover:-translate-y-0.5 hover:border-rose-200/25">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-rose-300/10 text-lg text-rose-100">{{ $workspace['icon'] }}</span>
                        <span class="preview-pill {{ $workspace['status'] === 'Aktif' ? 'preview-pill-active' : ($workspace['active'] ? '' : 'preview-pill-muted') }}">{{ $workspace['status'] }}</span>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold text-white">{{ $workspace['title'] }}</h2>
                    <p class="mt-2 flex-1 text-sm leading-6 text-slate-400">{{ $workspace['description'] }}</p>
                    <span class="mt-5 text-sm font-medium {{ $workspace['active'] ? 'text-rose-200' : 'text-slate-400' }}">
                        {{ $workspace['active'] ? 'Buka workspace' : 'Lihat rancangan' }} <span aria-hidden="true">→</span>
                    </span>
                </a>
            @endforeach
        </div>
        <p x-cloak x-show="appliedQuery && !Array.from($refs.workspaceCards.querySelectorAll('a[data-search]')).some(card => card.dataset.search.includes(appliedQuery))" class="mt-6 rounded-2xl border border-dashed border-white/15 p-7 text-center text-sm text-slate-400">Tidak ada workspace yang cocok dengan pencarian.</p>
    </div>
@endsection
