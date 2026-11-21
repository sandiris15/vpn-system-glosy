<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ForumTopic;
use App\Models\Server;
use App\Services\RouterOsMonitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');

        try {
            $snapshot = (new RouterOsMonitor())->snapshot($server);
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            $server->forceFill([
                'status' => 'offline',
                'last_checked_at' => now(),
            ])->save();

            Log::warning('RouterOS dashboard request failed.', [
                'server_id' => $server->getKey(),
                'exception' => $exception::class,
                'http_status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            return response()->view('errors.router-unavailable', [
                'selectedServer' => $server,
            ], 503);
        }

        $accounts = $snapshot->get('accounts');

        $server->forceFill([
            'status' => 'online',
            'last_checked_at' => now(),
        ])->save();

        $onlineCount = $accounts->where('is_online', true)->count();
        $activityLogs = AuditLog::query()
            ->with('user:id,name')
            ->where('server_id', $server->getKey())
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (AuditLog $log): array {
                $details = collect($log->metadata ?? [])
                    ->only(['remote_address', 'public_port', 'test_client_ip', 'ip_address', 'user_agent', 'script_mode'])
                    ->filter(fn (mixed $value): bool => $value !== null)
                    ->map(fn (mixed $value, string $key): string => match ($key) {
                        'remote_address' => 'IP '.$value,
                        'public_port' => 'port '.$value,
                        'test_client_ip' => 'client '.$value,
                        'ip_address' => 'IP asal '.$value,
                        'user_agent' => 'Perangkat/browser '.Str::limit((string) $value, 180),
                        'script_mode' => $value === 'vpn' ? 'mode VPN saja' : 'mode VPN + Speedtest',
                    })
                    ->implode(' · ');

                return [
                    'action' => match ($log->event) {
                        'auth.login' => 'Login berhasil',
                        'vpn_account.created' => 'Akun VPN dibuat',
                        'vpn_account.password_changed' => 'Password akun VPN diubah',
                        'vpn_account.deleted' => 'Akun VPN dihapus',
                        'vpn_account.script_generated' => 'Script client dibuat',
                        default => 'Aktivitas managed',
                    },
                    'description' => $log->event === 'auth.login'
                        ? ($details !== '' ? $details : 'Login berhasil')
                        : $log->subject.($details !== '' ? ' · '.$details : ''),
                    'actor' => $log->user?->name ?? 'Pengguna dihapus',
                    'created_at' => $log->created_at,
                ];
            });
        $recentAccounts = $accounts->sort(function (array $left, array $right): int {
            return ((int) $right['is_online'] <=> (int) $left['is_online'])
                ?: ((strtotime((string) ($right['created_at'] ?? '')) ?: 0)
                    <=> (strtotime((string) ($left['created_at'] ?? '')) ?: 0));
        })->take(8);
        $forumTopics = ForumTopic::query()
            ->with('user:id,name')
            ->withCount('replies')
            ->latest()
            ->limit(5)
            ->get();

        return view('managed.dashboard', [
            'selectedServer' => $server,
            'clusterServers' => $request->attributes->get('managedServers', collect([$server])),
            'stats' => [
                'total_vpn' => $accounts->count(),
                'active_vpn' => $onlineCount,
                'offline_vpn' => $accounts->count() - $onlineCount,
                'total_nat' => $snapshot->get('nat_count'),
            ],
            'recentAccounts' => $recentAccounts,
            'activityLogs' => $activityLogs,
            'forumTopics' => $forumTopics,
        ]);
    }
}
