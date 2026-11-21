<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceController extends Controller
{
    private const PLANNED_WORKSPACES = [
        'network-automation' => [
            'title' => 'Topologi, Interface & VLAN',
            'description' => 'Atur gambaran jalur jaringan, interface, VLAN, dan pemilihan sumber WAN.',
            'tools' => ['Topologi internet, router, VPN, dan pelanggan', 'Pemetaan port dan VLAN', 'Kontrol interface dan validasi'],
        ],
        'isp-failover' => [
            'title' => 'Auto Speedtest & Failover Multi-ISP',
            'description' => 'Pantau kualitas beberapa jalur ISP dan siapkan kebijakan failover.',
            'tools' => ['Target pengujian per ISP', 'Health-check gateway', 'Prioritas jalur cadangan'],
        ],
        'router-setup' => [
            'title' => 'Setup MikroTik & Port Publik',
            'description' => 'Rancang provisioning router, akses pelanggan, dan pemetaan port publik.',
            'tools' => ['Wizard identitas dan akses', 'WAN, VLAN, VPN, dan NAT', 'Review dan export konfigurasi'],
        ],
    ];

    public function index(Request $request): View
    {
        return view('managed.workspaces.index', [
            'selectedServer' => $request->attributes->get('selectedServer'),
        ]);
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
}
