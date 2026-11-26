<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use App\Services\RouterOsFailoverScriptGenerator;
use App\Services\RouterOsInterfaceScheduleScriptGenerator;
use App\Services\RouterOsScriptLibraryGenerator;
use App\Services\RouterOsSetupScriptGenerator;
use App\Support\WorkspaceCatalog;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceController extends Controller
{
    private const PLANNED_WORKSPACES = [
        'network-automation' => [
            'title' => 'Topologi, Interface & VLAN',
            'description' => 'Atur gambaran jalur jaringan, interface, VLAN, dan pemilihan sumber WAN.',
            'tools' => ['Topologi internet, router, VPN, dan pelanggan', 'Pemetaan port dan VLAN', 'Kontrol interface dan validasi'],
        ],
    ];

    public function index(Request $request): View
    {
        return view('managed.workspaces.index', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'workspaces' => WorkspaceCatalog::visibleTo($request->user()),
        ]);
    }

    public function ispFailover(Request $request): View
    {
        return view('managed.workspaces.isp-failover', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'generatedScript' => session('generatedIspFailoverScript'),
        ]);
    }

    public function generateIspFailoverScript(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gateway_isp1' => ['required', 'ipv4', 'different:gateway_isp2'],
            'gateway_isp2' => ['required', 'ipv4', 'different:gateway_isp1'],
        ], [
            'gateway_isp1.required' => 'Isi gateway ISP 1.',
            'gateway_isp1.ipv4' => 'Masukkan alamat IPv4 gateway ISP 1 yang valid.',
            'gateway_isp1.different' => 'Gateway ISP 1 dan ISP 2 harus berbeda.',
            'gateway_isp2.required' => 'Isi gateway ISP 2.',
            'gateway_isp2.ipv4' => 'Masukkan alamat IPv4 gateway ISP 2 yang valid.',
            'gateway_isp2.different' => 'Gateway ISP 1 dan ISP 2 harus berbeda.',
        ]);

        $script = (new RouterOsFailoverScriptGenerator())->generate(
            $validated['gateway_isp1'],
            $validated['gateway_isp2'],
        );

        return redirect()
            ->route('workspaces.isp-failover')
            ->withInput($validated)
            ->with('generatedIspFailoverScript', $script);
    }

    public function ethernetScheduler(Request $request): View
    {
        return $this->interfaceScheduler($request, 'ethernet');
    }

    public function vlanScheduler(Request $request): View
    {
        return $this->interfaceScheduler($request, 'vlan');
    }

    public function generateEthernetSchedule(Request $request, RouterOsInterfaceScheduleScriptGenerator $generator): RedirectResponse
    {
        return $this->generateInterfaceSchedule($request, $generator, 'ethernet', 'workspaces.ethernet-scheduler');
    }

    public function generateVlanSchedule(Request $request, RouterOsInterfaceScheduleScriptGenerator $generator): RedirectResponse
    {
        return $this->generateInterfaceSchedule($request, $generator, 'vlan', 'workspaces.vlan-scheduler');
    }

    public function scriptLibrary(Request $request): View
    {
        abort_if($request->user()->isOperator(), 403);

        return view('managed.workspaces.script-library', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'generatedScript' => session('generatedLibraryScript'),
            'generatedScriptLabel' => session('generatedLibraryScriptLabel'),
        ]);
    }

    public function generateLibraryScript(Request $request, RouterOsScriptLibraryGenerator $generator): RedirectResponse
    {
        abort_if($request->user()->isOperator(), 403);

        $scriptType = $request->input('script_type');
        $rules = [
            'script_type' => ['required', 'in:clear-ppp-addresses,backup-ppp-secrets'],
        ];
        $messages = [
            'confirm_clear.required' => 'Konfirmasi bahwa semua PPP Secret akan terdampak.',
            'confirm_clear.accepted' => 'Konfirmasi bahwa semua PPP Secret akan terdampak.',
            'backup_file.required' => 'Isi nama file backup.',
            'backup_file.regex' => 'Nama file hanya boleh berisi huruf, angka, garis bawah, dan tanda hubung.',
        ];

        if ($scriptType === 'clear-ppp-addresses') {
            $rules['confirm_clear'] = ['required', 'accepted'];
            $validated = $request->validate($rules, $messages);
            $script = $generator->clearPppSecretAddresses();
            $label = 'Hapus local-address dan remote-address semua PPP Secret';
        } else {
            $rules['backup_file'] = ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'];
            $validated = $request->validate($rules, $messages);
            $script = $generator->backupPppSecrets($validated['backup_file']);
            $label = 'Backup PPP Secret dengan password';
        }

        return redirect()
            ->route('workspaces.script-library')
            ->withInput($request->only('backup_file'))
            ->with('generatedLibraryScript', $script)
            ->with('generatedLibraryScriptLabel', $label);
    }

    public function mikrotikSetup(Request $request): View
    {
        abort_if($request->user()->isOperator(), 403);

        return view('managed.workspaces.mikrotik-setup', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'generatedScript' => null,
            'setupInput' => [],
        ]);
    }

    public function generateMikrotikSetup(Request $request, RouterOsSetupScriptGenerator $generator): Response
    {
        abort_if($request->user()->isOperator(), 403);

        $isVlan = $request->input('lan_mode') === 'vlan';
        $validator = Validator::make($request->all(), [
            'identity' => ['required', 'string', 'max:64'],
            'wan_interface' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'lan_mode' => ['required', 'in:ethernet,vlan'],
            'lan_interface' => $isVlan
                ? ['nullable']
                : ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'vlan_parent' => $isVlan
                ? ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/']
                : ['nullable'],
            'vlan_name' => $isVlan
                ? ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/']
                : ['nullable'],
            'vlan_id' => $isVlan
                ? ['required', 'integer', 'between:1,4094']
                : ['nullable'],
            'lan_address' => ['required', 'string', 'max:18'],
            'pppoe_pool_start' => ['required', 'ipv4'],
            'pppoe_pool_end' => ['required', 'ipv4'],
            'hotspot_pool_start' => ['required', 'ipv4'],
            'hotspot_pool_end' => ['required', 'ipv4'],
            'pppoe_service_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'hotspot_server_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'dns_servers' => ['required', 'in:cloudflare,google,both'],
            'admin_username' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'admin_password' => ['required', 'string', 'min:12', 'max:128'],
        ], [
            'wan_interface.regex' => 'Nama interface WAN hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'lan_interface.regex' => 'Nama interface LAN tidak valid.',
            'vlan_parent.regex' => 'Nama interface induk VLAN tidak valid.',
            'vlan_name.regex' => 'Nama VLAN hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'vlan_id.between' => 'VLAN ID harus antara 1 dan 4094.',
            'lan_address.max' => 'Alamat LAN harus berupa IPv4/prefix, contohnya 192.168.10.1/24.',
            'admin_password.min' => 'Password admin harus minimal 12 karakter.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('admin_password'));
        }

        $validated = $validator->validated();
        $setupInput = $validated;
        unset($setupInput['admin_password']);

        try {
            $script = $generator->generate($validated);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['configuration' => $exception->getMessage()])
                ->withInput($request->except('admin_password'));
        }

        return response()->view('managed.workspaces.mikrotik-setup', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'generatedScript' => $script,
            'setupInput' => $setupInput,
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function hotspotTemplate(Request $request): View
    {
        return view('managed.workspaces.hotspot-template', [
            'selectedServer' => $request->attributes->get('selectedServer'),
        ]);
    }

    public function planned(Request $request, string $workspace): View|Response
    {
        abort_unless(isset(self::PLANNED_WORKSPACES[$workspace]), 404);

        return view('managed.workspaces.planned', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'workspace' => self::PLANNED_WORKSPACES[$workspace],
        ]);
    }

    private function interfaceScheduler(Request $request, string $interfaceType): View
    {
        return view('managed.workspaces.interface-scheduler', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'interfaceType' => $interfaceType,
            'generatedScript' => session('generatedInterfaceScheduleScript'),
        ]);
    }

    private function generateInterfaceSchedule(
        Request $request,
        RouterOsInterfaceScheduleScriptGenerator $generator,
        string $interfaceType,
        string $routeName,
    ): RedirectResponse {
        $isOneTime = $request->input('schedule_mode') === 'once';
        $validated = $request->validate([
            'interface_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'schedule_mode' => ['required', 'in:daily,once'],
            'on_hour' => ['required', 'integer', 'between:0,23'],
            'on_minute' => ['required', 'integer', 'between:0,59'],
            'off_hour' => ['required', 'integer', 'between:0,23'],
            'off_minute' => ['required', 'integer', 'between:0,59'],
            'on_date' => $isOneTime
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:today']
                : ['nullable', 'date_format:Y-m-d'],
            'off_date' => $isOneTime
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:today']
                : ['nullable', 'date_format:Y-m-d'],
        ], [
            'interface_name.required' => 'Isi nama interface.',
            'interface_name.regex' => 'Nama interface hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'schedule_mode.in' => 'Pilih jadwal setiap hari atau sekali pada tanggal tertentu.',
            'on_hour.between' => 'Jam ON harus antara 0 dan 23.',
            'on_minute.between' => 'Menit ON harus antara 0 dan 59.',
            'off_hour.between' => 'Jam OFF harus antara 0 dan 23.',
            'off_minute.between' => 'Menit OFF harus antara 0 dan 59.',
            'on_date.required' => 'Pilih tanggal ON.',
            'on_date.date_format' => 'Format tanggal ON tidak valid.',
            'on_date.after_or_equal' => 'Tanggal ON tidak boleh sebelum hari ini.',
            'off_date.required' => 'Pilih tanggal OFF.',
            'off_date.date_format' => 'Format tanggal OFF tidak valid.',
            'off_date.after_or_equal' => 'Tanggal OFF tidak boleh sebelum hari ini.',
        ]);

        $onTime = sprintf('%02d:%02d', (int) $validated['on_hour'], (int) $validated['on_minute']);
        $offTime = sprintf('%02d:%02d', (int) $validated['off_hour'], (int) $validated['off_minute']);

        if ($onTime === $offTime) {
            return back()
                ->withInput($validated)
                ->withErrors(['off_minute' => 'Waktu ON dan OFF harus berbeda.']);
        }

        if ($isOneTime
            && new DateTimeImmutable($validated['off_date'].' '.$offTime)
                <= new DateTimeImmutable($validated['on_date'].' '.$onTime)) {
            return back()
                ->withInput($validated)
                ->withErrors(['off_date' => 'Tanggal dan waktu OFF harus setelah tanggal dan waktu ON.']);
        }

        $script = $generator->generate(
            $interfaceType,
            $validated['interface_name'],
            $validated['schedule_mode'],
            $onTime,
            $offTime,
            $validated['on_date'] ?? null,
            $validated['off_date'] ?? null,
        );

        return redirect()
            ->route($routeName)
            ->withInput($validated)
            ->with('generatedInterfaceScheduleScript', $script);
    }
}
