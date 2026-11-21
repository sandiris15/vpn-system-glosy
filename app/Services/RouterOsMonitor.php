<?php

namespace App\Services;

use App\Models\ManagedVpnAccount;
use App\Models\Server;
use Illuminate\Support\Collection;

class RouterOsMonitor
{
    public function snapshot(Server $server): Collection
    {
        $router = RouterOsRestClient::forServer($server);
        $secrets = $router->collection('ppp/secret');
        $activeSessions = $router->collection('ppp/active')->keyBy('name');
        $natRules = $router->collection('ip/firewall/nat')
            ->filter(fn (array $rule): bool => data_get($rule, 'chain') === 'dstnat'
                && data_get($rule, 'action') === 'dst-nat'
                && ! filter_var(data_get($rule, 'disabled', 'false'), FILTER_VALIDATE_BOOLEAN));
        $managedAccounts = ManagedVpnAccount::query()
            ->where('server_id', $server->getKey())
            ->get(['routeros_id', 'created_at'])
            ->keyBy('routeros_id');

        $accounts = $secrets->map(function (array $secret) use ($activeSessions, $natRules, $managedAccounts): array {
            $username = (string) data_get($secret, 'name', '');
            $remoteAddress = (string) data_get($secret, 'remote-address', '');
            $session = $activeSessions->get($username);
            $id = $secret['.id'] ?? null;
            $managedAccount = is_string($id) ? $managedAccounts->get($id) : null;

            return [
                'id' => $id,
                'username' => $username,
                'service' => data_get($secret, 'service'),
                'remote_address' => $remoteAddress,
                'is_managed' => is_string($secret['.id'] ?? null)
                    && data_get($secret, 'service') === 'sstp'
                    && (str_starts_with((string) data_get($secret, 'comment', ''), 'sstp-console:')
                        || $managedAccount !== null),
                'created_at' => $managedAccount?->created_at?->toIso8601String(),
                'is_online' => $session !== null,
                'uptime' => data_get($session, 'uptime', '—'),
                'nat_rules' => $natRules
                    ->filter(fn (array $rule): bool => $remoteAddress !== ''
                        && data_get($rule, 'to-addresses') === $remoteAddress)
                    ->map(fn (array $rule): array => [
                        'protocol' => data_get($rule, 'protocol', 'tcp'),
                        'public_port' => data_get($rule, 'dst-port', ''),
                        'internal_port' => data_get($rule, 'to-ports', ''),
                        'comment' => data_get($rule, 'comment', ''),
                    ])
                    ->values(),
            ];
        })->values();

        return collect([
            'accounts' => $accounts,
            'nat_count' => $accounts->sum(fn (array $account): int => $account['nat_rules']->count()),
        ]);
    }
}
