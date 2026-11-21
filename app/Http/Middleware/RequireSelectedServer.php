<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireSelectedServer
{
    public function handle(Request $request, Closure $next): Response
    {
        $servers = $request->user()
            ->servers()
            ->where('servers.is_active', true)
            ->orderBy('servers.priority')
            ->orderBy('servers.id')
            ->get();

        if ($servers->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun ini belum terhubung ke cluster CHR yang aktif.']);
        }

        $request->session()->forget('selected_server_id');
        $request->attributes->set('managedServers', $servers);
        $request->attributes->set('selectedServer', $servers->first());

        return $next($request);
    }
}
