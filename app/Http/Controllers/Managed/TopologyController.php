<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use App\Models\Topology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TopologyController extends Controller
{
    public function index(Request $request): View
    {
        return view('managed.workspaces.topology-index', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'topologies' => Topology::query()->with('user:id,name')->latest()->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $topology = Topology::query()->create([
            'user_id' => $request->user()->getKey(),
            'name' => $validated['name'],
            'password_hash' => Hash::make($validated['password']),
            'design' => [],
        ]);
        $request->session()->put('topology_edit.'.$topology->getKey(), $request->user()->getKey());

        return redirect()->route('workspaces.topology.show', $topology)
            ->with('status', 'Topologi dibuat. Simpan perubahan setelah menggambar.');
    }

    public function show(Request $request, Topology $topology): View
    {
        return view('managed.workspaces.topology-editor', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'topology' => $topology,
            'canEdit' => $request->session()->get('topology_edit.'.$topology->getKey()) === $request->user()->getKey(),
        ]);
    }

    public function unlock(Request $request, Topology $topology): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'max:128']]);

        if (! Hash::check($validated['password'], $topology->password_hash)) {
            return back()->withErrors(['password' => 'Password topologi salah.']);
        }

        $request->session()->put('topology_edit.'.$topology->getKey(), $request->user()->getKey());

        return redirect()->route('workspaces.topology.show', $topology)
            ->with('status', 'Topologi terbuka untuk diedit selama sesi login ini.');
    }

    public function update(Request $request, Topology $topology): JsonResponse
    {
        abort_unless($request->session()->get('topology_edit.'.$topology->getKey()) === $request->user()->getKey(), 403);

        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'design' => ['present', 'array', 'list', 'max:200'],
            'design.*' => ['required', 'array:type,x,y,x2,y2,text'],
            'design.*.type' => ['required', Rule::in(['rectangle', 'ellipse', 'text', 'line'])],
            'design.*.x' => ['required', 'numeric', 'between:0,1000'],
            'design.*.y' => ['required', 'numeric', 'between:0,600'],
            'design.*.x2' => ['required_if:design.*.type,line', 'numeric', 'between:0,1000'],
            'design.*.y2' => ['required_if:design.*.type,line', 'numeric', 'between:0,600'],
            'design.*.text' => ['required_unless:design.*.type,line', 'nullable', 'string', 'max:80'],
        ]);
        $design = array_map(function (array $item): array {
            $result = ['type' => $item['type'], 'x' => (float) $item['x'], 'y' => (float) $item['y']];

            if ($item['type'] === 'line') {
                $result['x2'] = (float) $item['x2'];
                $result['y2'] = (float) $item['y2'];
            } else {
                $result['text'] = $item['text'];
            }

            return $result;
        }, $validated['design']);

        $updated = Topology::query()
            ->whereKey($topology->getKey())
            ->where('revision', $validated['revision'])
            ->update([
                'design' => json_encode($design, JSON_THROW_ON_ERROR),
                'revision' => $validated['revision'] + 1,
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'Topologi telah diubah pengguna lain. Muat ulang sebelum menyimpan.'], 409);
        }

        return response()->json(['revision' => $validated['revision'] + 1]);
    }
}
