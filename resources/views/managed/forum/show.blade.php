@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', $topic->title)
@section('breadcrumb', 'Workspaces / Forum Diskusi')
@section('mobile-heading', 'Forum Diskusi')

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('forum.index') }}" class="text-sm text-slate-400 hover:text-white">← Forum Diskusi</a>
        <article class="glass-panel mt-5 p-5 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs text-slate-400">{{ $topic->user?->name ?? 'Pengguna dihapus' }} · {{ $topic->created_at->diffForHumans() }}</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">{{ $topic->title }}</h1>
                </div>
                @if($canModerate)
                    <form method="POST" action="{{ route('forum.topics.destroy', $topic) }}" onsubmit="return confirm('Hapus topik dan seluruh balasannya?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="button-table button-table-danger">Hapus topik</button>
                    </form>
                @endif
            </div>
            <div class="mt-5 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">{{ $topic->body }}</div>
        </article>

        <section class="mt-6 space-y-3" aria-label="Balasan">
            <h2 class="font-semibold text-white">Balasan ({{ $topic->replies->count() }})</h2>
            @forelse($topic->replies as $reply)
                <article class="glass-panel p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-slate-400">{{ $reply->user?->name ?? 'Pengguna dihapus' }} · {{ $reply->created_at->diffForHumans() }}</p>
                        @if($canModerate)
                            <form method="POST" action="{{ route('forum.replies.destroy', $reply) }}" onsubmit="return confirm('Hapus balasan ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="button-table button-table-danger">Hapus</button>
                            </form>
                        @endif
                    </div>
                    <div class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-200">{{ $reply->body }}</div>
                </article>
            @empty
                <p class="rounded-xl border border-white/10 p-4 text-sm text-slate-400">Belum ada balasan.</p>
            @endforelse
        </section>

        <section class="glass-panel mt-6 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Tulis balasan</h2>
            <form method="POST" action="{{ route('forum.replies.store', $topic) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="body" class="field-label">Balasan</label>
                    <textarea id="body" name="body" rows="4" maxlength="20000" required class="input-glass" placeholder="Tulis balasan yang membantu">{{ old('body') }}</textarea>
                    @error('body')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="button-primary">Kirim balasan</button>
            </form>
        </section>
    </div>
@endsection
