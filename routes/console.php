<?php

use App\Models\Server;
use App\Services\RouterOsClusterVpnAccountService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('about:deployment', function (): void {
    $this->comment('SSTP Console application scaffold is installed.');
})->purpose('Display the deployment scaffold status');

Artisan::command('vpn:sync-cluster', function (RouterOsClusterVpnAccountService $accounts): void {
    $servers = Server::query()
        ->where('is_active', true)
        ->orderBy('priority')
        ->orderBy('id')
        ->get();

    if ($servers->count() < 2) {
        $this->error('Configure at least one backup server before synchronizing the cluster.');

        return;
    }

    $result = $accounts->synchronizeManagedAccounts($servers);
    $this->info("Checked {$result['accounts']} managed accounts; created {$result['replicas_created']} replicas.");
})->purpose('Synchronize application-managed VPN accounts to backup routers');
