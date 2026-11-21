<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\AuditLogger;
use App\Services\AuditLoggingException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $loginField = filter_var($validated['email'], FILTER_VALIDATE_EMAIL) !== false
            ? 'email'
            : 'username';

        if (! Auth::attempt(
            [$loginField => $validated['email'], 'password' => $validated['password']],
            $request->boolean('remember'),
        )) {
            return back()
                ->withErrors(['email' => 'Kredensial atau server tidak valid.'])
                ->onlyInput('email');
        }

        /** @var Server|null $server */
        $server = $request->user()
            ->servers()
            ->where('servers.is_active', true)
            ->orderBy('servers.priority')
            ->orderBy('servers.id')
            ->first();

        if (! $server instanceof Server) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Kredensial atau server tidak valid.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $rawUserAgent = $request->userAgent();
        $userAgent = is_string($rawUserAgent)
            ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', mb_scrub($rawUserAgent, 'UTF-8')) ?? ''), 0, 512)
            : null;
        $userAgent = $userAgent !== '' ? $userAgent : null;

        try {
            $audit->record(
                $request->user(),
                $server,
                'auth.login',
                $request->user()->name,
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $userAgent,
                ],
                'Login tidak dapat diselesaikan karena log aktivitas gagal disimpan. Silakan coba lagi.',
            );
        } catch (AuditLoggingException $exception) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => $exception->getMessage()])
                ->onlyInput('email');
        }

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
