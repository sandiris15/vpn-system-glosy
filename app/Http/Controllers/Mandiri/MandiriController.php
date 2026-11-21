<?php

namespace App\Http\Controllers\Mandiri;

use App\Http\Controllers\Controller;
use App\Services\RouterOsHostResolver;
use App\Services\RouterOsMandiriProvisioner;
use App\Services\RouterOsProvisioningException;
use App\Services\RouterOsRestClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class MandiriController extends Controller
{
    private const SESSION_TTL_MINUTES = 15;

    public function connection(): View
    {
        return view('mandiri.connection');
    }

    public function connect(Request $request, RouterOsHostResolver $hosts): RedirectResponse
    {
        $request->session()->forget('mandiri_connection');
        $validator = Validator::make($request->all(), [
            'host' => ['required', 'string', 'max:253'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'router_password' => ['required', 'string', 'max:255'],
            'verify_tls' => ['required', 'accepted'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('router_password'));
        }

        $validated = $validator->validated();

        try {
            $resolved = $hosts->resolvePublicHost($validated['host']);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput($request->except('router_password'))
                ->withErrors(['host' => $exception->getMessage()]);
        }

        $port = (int) $validated['port'];

        try {
            $identity = RouterOsRestClient::forTemporaryConnection(
                $resolved['host'],
                $port,
                $validated['username'],
                $validated['router_password'],
                $resolved['resolved_ip'],
            )->collection('system/identity');

            if ($identity->isEmpty()) {
                throw new UnexpectedValueException('RouterOS returned an empty identity response.');
            }
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            Log::warning('Mandiri RouterOS connection failed.', [
                'exception' => $exception::class,
                'host' => $resolved['host'],
                'http_status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            return back()
                ->withInput($request->except('router_password'))
                ->withErrors(['connection' => 'Koneksi HTTPS atau verifikasi RouterOS gagal. Periksa host, port, user, dan sertifikat TLS.']);
        }

        $request->session()->regenerate();
        $request->session()->put('mandiri_connection', [
            'host' => $resolved['host'],
            'port' => $port,
            'username' => $validated['username'],
            'password' => $validated['router_password'],
            'resolved_ip' => $resolved['resolved_ip'],
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->route('mandiri.provision.create')
            ->with('status', 'Koneksi HTTPS berhasil. Sesi Mandiri berakhir otomatis dalam 15 menit.');
    }

    public function provisionForm(Request $request): View
    {
        $connection = $request->attributes->get('mandiriConnection');

        return view('mandiri.provision', [
            'routerHost' => $connection['host'],
        ]);
    }

    public function provision(Request $request, RouterOsMandiriProvisioner $provisioner): View|Response
    {
        $connection = $request->attributes->get('mandiriConnection');
        $validator = Validator::make($request->all(), [
            'vpn_username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'vpn_password' => ['required', 'string', 'min:8', 'max:255'],
            'local_address' => ['required', 'ip:version4', 'different:remote_address'],
            'remote_address' => ['required', 'ip:version4', 'different:local_address'],
            'sstp_port' => ['required', 'integer', 'between:1,65535'],
            'add_default_route' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('vpn_password'));
        }

        $validated = $validator->validated();

        if (preg_match('/[\x00-\x1F\x7F]/', $validated['vpn_password']) === 1) {
            return back()
                ->withInput($request->except('vpn_password'))
                ->withErrors(['vpn_password' => 'Password tidak boleh mengandung karakter kontrol.']);
        }

        $input = [
            'vpn_username' => $validated['vpn_username'],
            'vpn_password' => $validated['vpn_password'],
            'local_address' => $validated['local_address'],
            'remote_address' => $validated['remote_address'],
            'sstp_port' => (int) $validated['sstp_port'],
            'add_default_route' => (bool) ($validated['add_default_route'] ?? false),
        ];

        try {
            $result = $provisioner->provision($connection, $input);
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Mandiri RouterOS provisioning request failed.', [
                'exception' => $exception::class,
                'host' => $connection['host'],
                'http_status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            return back()
                ->withInput($request->except('vpn_password'))
                ->withErrors(['provisioning' => 'Koneksi ke router gagal. Periksa REST API dan sertifikat TLS.']);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput($request->except('vpn_password'))
                ->withErrors(['provisioning' => $exception->getMessage()]);
        } catch (UnexpectedValueException $exception) {
            Log::warning('Mandiri RouterOS provisioning response was invalid.', [
                'exception' => $exception::class,
                'host' => $connection['host'],
            ]);

            return back()
                ->withInput($request->except('vpn_password'))
                ->withErrors(['provisioning' => 'Respons RouterOS tidak sesuai. Periksa PPP secret dan coba lagi.']);
        } catch (RouterOsProvisioningException $exception) {
            return back()
                ->withInput($request->except('vpn_password'))
                ->withErrors(['provisioning' => $exception->getMessage()]);
        } catch (LockTimeoutException) {
            return back()
                ->withInput($request->except('vpn_password'))
                ->withErrors(['provisioning' => 'Provisioning lain sedang berjalan. Tunggu sebentar lalu coba lagi.']);
        }

        return response()->view('mandiri.provision', [
            'routerHost' => $connection['host'],
            'generatedScript' => $result['script'],
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('mandiri_connection');
        $request->session()->regenerate();

        return redirect()->route('mandiri.connection')
            ->with('status', 'Sesi Mandiri diakhiri dan kredensial sementara dihapus.');
    }
}
