<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMandiriSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $connection = $request->session()->get('mandiri_connection');

        if (! is_array($connection)
            || ! is_int($connection['expires_at'] ?? null)
            || $connection['expires_at'] <= now()->timestamp) {
            $request->session()->forget('mandiri_connection');

            return redirect()
                ->route('mandiri.connection')
                ->withErrors(['connection' => 'Sesi router telah berakhir. Hubungkan kembali untuk melanjutkan.']);
        }

        $request->attributes->set('mandiriConnection', $connection);

        return $next($request);
    }
}
