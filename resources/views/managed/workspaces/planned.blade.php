@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', data_get($workspace, 'title', 'Workspace'))
@section('breadcrumb', 'Workspaces / '.data_get($workspace, 'title', 'Workspace'))
@section('mobile-heading', data_get($workspace, 'title', 'Workspace'))

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
        <section class="glass-panel mt-5 p-6 sm:p-8">
            <span class="preview-pill preview-pill-muted">Rancangan · belum aktif</span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">{{ data_get($workspace, 'title') }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-400">{{ data_get($workspace, 'description') }}</p>
            <h2 class="mt-7 font-semibold text-white">Tool yang direncanakan</h2>
            <ul class="mt-3 space-y-3">
                @foreach(data_get($workspace, 'tools', []) as $tool)
                    <li class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[.03] p-3 text-sm text-slate-300">
                        <span class="text-rose-200" aria-hidden="true">•</span>{{ $tool }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-6 rounded-xl border border-amber-200/15 bg-amber-200/[.05] p-4 text-xs leading-5 text-amber-100/80">Workspace ini belum mengubah konfigurasi router. Tool aktif akan ditambahkan setelah kebutuhan dan aturan penerapannya ditentukan.</p>
        </section>
    </div>
@endsection
