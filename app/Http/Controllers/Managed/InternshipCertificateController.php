<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use App\Models\InternshipCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InternshipCertificateController extends Controller
{
    public function index(Request $request): View
    {
        return view('managed.workspaces.internship-certificates', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'certificates' => InternshipCertificate::query()->latest('id')->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'program' => ['required', Rule::in(['magang', 'pkl'])],
            'location' => ['required', 'string', 'max:120'],
            'institution' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'issued_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $certificate = InternshipCertificate::query()->create($validated + ['user_id' => $request->user()->getKey()]);

        return redirect()->route('workspaces.internship-certificates.show', $certificate)
            ->with('status', 'Sertifikat berhasil disimpan dan siap diunduh.');
    }

    public function show(Request $request, InternshipCertificate $certificate): View
    {
        return view('managed.workspaces.internship-certificate-show', [
            'selectedServer' => $request->attributes->get('selectedServer'),
            'certificate' => $certificate,
        ]);
    }

    public function asset(string $asset): BinaryFileResponse
    {
        abort_unless(in_array($asset, ['logo', 'signature'], true), 404);

        return response()->file(resource_path('certificate/'.$asset.'.png'), [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
