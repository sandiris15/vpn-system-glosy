<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ForumController extends Controller
{
    public function index(Request $request): View
    {
        return view('managed.forum.index', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'topics' => ForumTopic::query()
                ->with('user:id,name')
                ->withCount('replies')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $topic = ForumTopic::query()->create([
            ...$validated,
            'user_id' => $request->user()->getKey(),
        ]);

        return redirect()->route('forum.show', $topic)->with('status', 'Topik diskusi berhasil dibuat.');
    }

    public function show(Request $request, ForumTopic $topic): View
    {
        $topic->load([
            'user:id,name',
            'replies' => fn ($query) => $query->with('user:id,name')->oldest(),
        ]);

        return view('managed.forum.show', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'topic' => $topic,
            'canModerate' => $request->user()->role === 'admin',
        ]);
    }

    public function reply(Request $request, ForumTopic $topic): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $topic->replies()->create([
            ...$validated,
            'user_id' => $request->user()->getKey(),
        ]);

        return redirect()->route('forum.show', $topic)->with('status', 'Balasan berhasil dikirim.');
    }

    public function destroyTopic(Request $request, ForumTopic $topic): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $topic->delete();

        return redirect()->route('forum.index')->with('status', 'Topik dan balasannya dihapus oleh moderator.');
    }

    public function destroyReply(Request $request, ForumReply $reply): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $topic = $reply->topic;
        $reply->delete();

        return redirect()->route('forum.show', $topic)->with('status', 'Balasan dihapus oleh moderator.');
    }
}
