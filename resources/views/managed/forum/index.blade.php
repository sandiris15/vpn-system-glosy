@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Forum Diskusi')
@section('breadcrumb', 'Workspaces / Forum Diskusi')
@section('mobile-heading', 'Forum Diskusi')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Forum Diskusi</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Ruang diskusi internal untuk pengguna aplikasi yang sudah login.</p>
        </div>
    </div>

    <section class="glass-panel mb-6 p-5 sm:p-6">
        <h2 class="font-semibold text-white">Buat topik baru</h2>
        <form method="POST" action="{{ route('forum.topics.store') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="title" class="field-label">Judul</label>
                <input id="title" name="title" value="{{ old('title') }}" maxlength="180" required class="input-glass" placeholder="Ringkas topik yang ingin dibahas">
                @error('title')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="body" class="field-label">Isi diskusi</label>
                <textarea id="body" name="body" rows="4" maxlength="20000" required class="input-glass" placeholder="Jelaskan konteks dan pertanyaan diskusi">{{ old('body') }}</textarea>
                @error('body')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="button-primary">Buat topik</button>
        </form>
    </section>

    <section class="glass-panel overflow-hidden">
        <div class="border-b border-white/10 px-5 py-5 sm:px-6">
            <h2 class="font-semibold text-white">Topik terbaru</h2>
            <p class="mt-1 text-xs text-slate-400">Konten forum hanya terlihat setelah login ke aplikasi.</p>
        </div>
        <div class="divide-y divide-white/[.07]">
            @forelse($topics as $topic)
                <article class="flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <a href="{{ route('forum.show', $topic) }}" class="font-medium text-white hover:text-rose-100">{{ $topic->title }}</a>
                        <p class="mt-1 text-xs text-slate-400">Oleh {{ $topic->user?->name ?? 'Pengguna dihapus' }} · {{ $topic->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="preview-pill preview-pill-muted">{{ $topic->replies_count }} balasan</span>
                </article>
            @empty
                <p class="px-5 py-12 text-center text-sm text-slate-400">Belum ada topik. Jadilah yang pertama memulai diskusi.</p>
            @endforelse
        </div>
        @if($topics->hasPages())
            <div class="border-t border-white/10 px-5 py-4">{{ $topics->links() }}</div>
        @endif
    </section>
@endsection
