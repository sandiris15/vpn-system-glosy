<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\AuditLogger;
use App\Services\AuditLoggingException;
use App\Services\RouterOsMonitor;
use App\Services\RouterOsClusterVpnAccountService;
use App\Services\RouterOsProvisioningException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class VpnAccountController extends Controller
{
    public function index(Request $request): View|Response
    {
        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');

        try {
            $accounts = (new RouterOsMonitor())->snapshot($server)->get('accounts');
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            return $this->routerUnavailable($server, $exception);
        }

        $latestManagedAccount = $accounts
            ->filter(fn (array $account): bool => $account['is_managed'] && is_string($account['created_at'] ?? null))
            ->sortByDesc('created_at')
            ->first();

        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $status = $request->query('status');

        if ($search !== '') {
            $accounts = $accounts->filter(fn (array $account): bool => str_contains(
                mb_strtolower($account['username'].' '.$account['remote_address']),
                $search,
            ));
        }

        if (in_array($status, ['online', 'offline'], true)) {
            $isOnline = $status === 'online';
            $accounts = $accounts->filter(fn (array $account): bool => $account['is_online'] === $isOnline);
        }

        $accounts = $accounts->sort(function (array $left, array $right): int {
            $leftCreatedAt = is_string($left['created_at'] ?? null) ? (strtotime($left['created_at']) ?: 0) : 0;
            $rightCreatedAt = is_string($right['created_at'] ?? null) ? (strtotime($right['created_at']) ?: 0) : 0;

            return ($rightCreatedAt <=> $leftCreatedAt)
                ?: ((int) $right['is_managed'] <=> (int) $left['is_managed']);
        })->values();

        return view('managed.vpn.index', [
            'selectedServer' => $server,
            'accounts' => $accounts,
            'latestManagedAccount' => $latestManagedAccount,
            'canModifyAccounts' => ! $request->user()->isOperator(),
            'primaryOnly' => config('managed.vpn_primary_only'),
        ]);
    }

    public function create(Request $request): View
    {
        $servers = $this->accountServers($request);

        return view('managed.vpn.create', [
            'selectedServer' => $servers->first(),
            'clusterServerCount' => $servers->count(),
            'primaryOnly' => config('managed.vpn_primary_only'),
        ]);
    }

    public function store(Request $request, RouterOsClusterVpnAccountService $accounts, AuditLogger $audit): RedirectResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');
        $servers = $this->accountServers($request);
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'protocol' => ['nullable', 'in:tcp,udp'],
            'internal_port' => ['nullable', 'in:8291,8728,80,custom'],
            'custom_internal_port' => ['nullable', 'integer', 'between:1,65535'],
            'manual_comment' => ['nullable', 'string', 'max:191', 'regex:/^[^\r\n]+$/'],
            'confirm' => ['required', 'accepted'],
        ]);
        $protocol = $validated['protocol'] ?? null;
        $internalPort = $validated['internal_port'] ?? null;

        if ($protocol !== null && ($internalPort === null || ($internalPort === 'custom' && ! isset($validated['custom_internal_port'])))) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['internal_port' => 'Pilih layanan internal atau isi port manual.']);
        }

        if ($protocol === null && ($internalPort !== null || isset($validated['manual_comment']))) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['protocol' => 'Pilih protokol untuk membuat aturan DST-NAT.']);
        }

        if (($internalPort !== 'custom' && ($validated['custom_internal_port'] ?? null) !== null)
            || ($protocol === null && ($validated['custom_internal_port'] ?? null) !== null)) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['custom_internal_port' => 'Port manual hanya dapat digunakan ketika layanan manual dipilih.']);
        }

        $manualComment = trim((string) ($validated['manual_comment'] ?? ''));

        if ($internalPort === 'custom' && $manualComment === '') {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['manual_comment' => 'Isi comment manual untuk port internal khusus.']);
        }

        if ($internalPort !== 'custom' && $manualComment !== '') {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['manual_comment' => 'Comment manual hanya dapat digunakan untuk port internal khusus.']);
        }

        $natComment = match ($internalPort) {
            '8291' => $validated['username'].'-winbox',
            '8728' => $validated['username'].'-api',
            '80' => $validated['username'].'-webfig',
            'custom' => $manualComment,
            default => null,
        };

        try {
            $created = $accounts->provision($servers, [
                'username' => $validated['username'],
                'password' => $validated['password'],
                'protocol' => $protocol,
                'public_port' => null,
                'internal_port' => $protocol === null
                    ? null
                    : (int) ($internalPort === 'custom'
                        ? $validated['custom_internal_port']
                        : $internalPort),
                'local_address' => config('managed.vpn_local_address'),
                'nat_comment' => $natComment,
            ]);
        } catch (ConnectionException|RequestException $exception) {
            $this->logRouterError($server, $exception);

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['provisioning' => 'Koneksi ke CHR gagal. Periksa REST API, sertifikat TLS, dan coba lagi.']);
        } catch (LockTimeoutException) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['provisioning' => 'Provisioning lain sedang berjalan pada CHR ini. Tunggu sebentar lalu coba lagi.']);
        } catch (InvalidArgumentException|OutOfBoundsException $exception) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['provisioning' => $exception->getMessage()]);
        } catch (UnexpectedValueException $exception) {
            $this->logRouterError($server, $exception);

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['provisioning' => 'Respons RouterOS tidak sesuai. Periksa konfigurasi dan status akun di CHR.']);
        } catch (RouterOsProvisioningException $exception) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['provisioning' => $exception->getMessage()]);
        }

        try {
            $audit->record($request->user(), $server, 'vpn_account.created', $created['username'], [
                'remote_address' => $created['remote_address'],
                'protocol' => $protocol,
                'public_port' => $created['public_port'] ?? null,
                'internal_port' => $created['internal_port'] ?? null,
            ]);
        } catch (AuditLoggingException $exception) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['provisioning' => $exception->getMessage()]);
        }

        return redirect()->route('vpn-accounts.show', $created['id'])
            ->with('status', 'Akun VPN dan konfigurasi RouterOS berhasil dibuat.');
    }

    public function show(Request $request, string $vpnAccount): View|Response
    {
        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');
        $servers = $this->accountServers($request);

        try {
            $account = (new RouterOsMonitor())->snapshot($server)->get('accounts')
                ->first(fn (array $entry): bool => (string) $entry['id'] === $vpnAccount);
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            return $this->routerUnavailable($server, $exception);
        }

        abort_unless(is_array($account), 404);

        return view('managed.vpn.show', [
            'selectedServer' => $server,
            'managedServers' => $servers,
            'account' => $account,
            'clusterServerCount' => $servers->count(),
            'primaryOnly' => config('managed.vpn_primary_only'),
        ]);
    }

    public function script(Request $request, string $vpnAccount, RouterOsClusterVpnAccountService $accounts, AuditLogger $audit): View|Response
    {
        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');
        $servers = $this->accountServers($request);
        $validated = $request->validate([
            'script_mode' => ['nullable', 'string', 'in:vpn,speedtest'],
        ]);
        $scriptMode = $validated['script_mode'] ?? 'speedtest';

        try {
            $result = $accounts->generateScript($servers, $vpnAccount, $scriptMode === 'speedtest');
            $account = (new RouterOsMonitor())->snapshot($server)->get('accounts')
                ->first(fn (array $entry): bool => (string) $entry['id'] === $vpnAccount);

            if (! is_array($account)) {
                throw new UnexpectedValueException('The generated script account could not be refreshed from RouterOS.');
            }
        } catch (ConnectionException|RequestException $exception) {
            $this->logRouterError($server, $exception);

            return back()
                ->withInput()
                ->withErrors(['script' => 'Koneksi ke CHR gagal. Periksa REST API dan sertifikat TLS.']);
        } catch (InvalidArgumentException $exception) {
            abort(404);
        } catch (UnexpectedValueException $exception) {
            $this->logRouterError($server, $exception);

            return back()
                ->withInput()
                ->withErrors(['script' => 'RouterOS tidak menyediakan data akun yang diperlukan untuk membuat script.']);
        }

        try {
            $audit->record($request->user(), $server, 'vpn_account.script_generated', $result['account']['username'], [
                'script_mode' => $scriptMode,
            ]);
        } catch (AuditLoggingException $exception) {
            return back()
                ->withInput()
                ->withErrors(['script' => $exception->getMessage()]);
        }

        return response()->view('managed.vpn.show', [
            'selectedServer' => $server,
            'managedServers' => $servers,
            'account' => $account,
            'generatedScript' => $result['script'],
            'scriptMode' => $scriptMode,
            'clusterServerCount' => $servers->count(),
            'primaryOnly' => config('managed.vpn_primary_only'),
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function edit(Request $request, string $vpnAccount): View|Response
    {
        abort_if($request->user()->isOperator(), 403);

        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');

        try {
            $account = (new RouterOsMonitor())->snapshot($server)->get('accounts')
                ->first(fn (array $entry): bool => (string) $entry['id'] === $vpnAccount);
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            return $this->routerUnavailable($server, $exception);
        }

        abort_unless(is_array($account) && $account['is_managed'], 404);

        return view('managed.vpn.edit', [
            'selectedServer' => $server,
            'account' => $account,
        ]);
    }

    public function update(Request $request, string $vpnAccount, RouterOsClusterVpnAccountService $accounts, AuditLogger $audit): RedirectResponse
    {
        abort_if($request->user()->isOperator(), 403);

        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');
        $servers = $this->accountServers($request);
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        try {
            $username = $accounts->updatePassword($servers, $vpnAccount, $validated['password']);
        } catch (ConnectionException|RequestException $exception) {
            $this->logRouterError($server, $exception);

            return back()->withErrors(['password' => 'Koneksi ke CHR gagal. Periksa REST API dan sertifikat TLS.']);
        } catch (RouterOsProvisioningException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        } catch (UnexpectedValueException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            abort(404);
        }

        try {
            $audit->record($request->user(), $server, 'vpn_account.password_changed', $username);
        } catch (AuditLoggingException $exception) {
            return back()->withErrors(['password' => $exception->getMessage()]);
        }

        return redirect()->route('vpn-accounts.index')->with('status', 'Password akun VPN berhasil diperbarui.');
    }

    public function destroy(Request $request, string $vpnAccount, RouterOsClusterVpnAccountService $accounts, AuditLogger $audit): RedirectResponse
    {
        abort_if($request->user()->isOperator(), 403);

        /** @var Server $server */
        $server = $request->attributes->get('selectedServer');
        $servers = $this->accountServers($request);

        try {
            $deleted = $accounts->delete($servers, $vpnAccount);
        } catch (ConnectionException|RequestException $exception) {
            $this->logRouterError($server, $exception);

            return back()->withErrors(['account' => 'Koneksi ke CHR gagal. Periksa REST API dan sertifikat TLS.']);
        } catch (InvalidArgumentException $exception) {
            abort(404);
        } catch (UnexpectedValueException $exception) {
            $this->logRouterError($server, $exception);

            return back()->withErrors(['account' => 'RouterOS belum mengonfirmasi penghapusan. Periksa akun dan NAT secara manual.']);
        } catch (RouterOsProvisioningException $exception) {
            return back()->withErrors(['account' => $exception->getMessage()]);
        }

        try {
            $audit->record($request->user(), $server, 'vpn_account.deleted', $deleted['username'], [
                'remote_address' => $deleted['remote_address'],
            ]);
        } catch (AuditLoggingException $exception) {
            return back()->withErrors(['account' => $exception->getMessage()]);
        }

        return redirect()->route('vpn-accounts.index')->with('status', 'Akun VPN berhasil dihapus.');
    }

    private function routerUnavailable(Server $server, ConnectionException|RequestException|UnexpectedValueException $exception): Response
    {
        $this->logRouterError($server, $exception);

        return response()->view('errors.router-unavailable', [
            'selectedServer' => $server,
        ], 503);
    }

    /**
     * @return Collection<int, Server>
     */
    private function accountServers(Request $request): Collection
    {
        /** @var Collection<int, Server> $servers */
        $servers = $request->attributes->get('managedServers');

        return config('managed.vpn_primary_only')
            ? $servers->take(1)->values()
            : $servers;
    }

    private function logRouterError(Server $server, ConnectionException|RequestException|UnexpectedValueException $exception): void
    {
        Log::warning('RouterOS account request failed.', [
            'server_id' => $server->getKey(),
            'exception' => $exception::class,
            'http_status' => $exception instanceof RequestException
                ? $exception->response->status()
                : null,
        ]);
    }
}
