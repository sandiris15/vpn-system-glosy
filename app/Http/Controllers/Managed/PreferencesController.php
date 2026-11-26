<?php

namespace App\Http\Controllers\Managed;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PreferencesController extends Controller
{
    private const PRESETS = [
        ['id' => 'rose-dawn', 'name' => 'Rose Dawn', 'preview_url' => '/images/wallpapers/rose-dawn.svg'],
        ['id' => 'violet-night', 'name' => 'Violet Night', 'preview_url' => '/images/wallpapers/violet-night.svg'],
        ['id' => 'ocean-glow', 'name' => 'Ocean Glow', 'preview_url' => '/images/wallpapers/ocean-glow.svg'],
    ];

    public function index(Request $request): View
    {
        $preference = $request->user()->preference()->firstOrNew([], [
            'wallpaper_id' => 'rose-dawn',
            'blur' => 8,
            'overlay' => 45,
        ]);
        $wallpapers = self::PRESETS;

        if ($preference->wallpaper_upload_path !== null) {
            $wallpapers[] = [
                'id' => 'custom',
                'name' => 'Unggahan saya',
                'preview_url' => route('preferences.wallpaper'),
            ];
        }

        return view('preferences.index', [
            'preference' => $preference,
            'wallpapers' => $wallpapers,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'wallpaper' => ['nullable', 'in:rose-dawn,violet-night,ocean-glow,custom'],
            'wallpaper_upload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
                'dimensions:max_width=3840,max_height=2160',
            ],
            'blur' => ['required', 'integer', 'between:0,24'],
            'overlay' => ['required', 'integer', 'between:0,80'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('wallpaper_upload'));
        }

        $validated = $validator->validated();
        $upload = $request->file('wallpaper_upload');
        $preference = $request->user()->preference()->firstOrNew();
        $oldPath = $preference->wallpaper_upload_path;
        $newPath = null;

        if ($upload !== null) {
            $newPath = $upload->store('wallpapers/'.$request->user()->getKey(), 'local');

            if (! is_string($newPath)) {
                return back()->withErrors(['wallpaper_upload' => 'Wallpaper gagal disimpan. Coba lagi.']);
            }
        } elseif (($validated['wallpaper'] ?? null) === null) {
            return back()
                ->withInput()
                ->withErrors(['wallpaper' => 'Pilih preset atau unggah wallpaper.']);
        } elseif ($validated['wallpaper'] === 'custom' && $oldPath === null) {
            return back()
                ->withInput()
                ->withErrors(['wallpaper' => 'Unggah wallpaper sebelum memilih opsi kustom.']);
        }

        $preference->fill([
            'wallpaper_id' => $upload !== null ? 'custom' : $validated['wallpaper'],
            'wallpaper_upload_path' => $newPath ?? ($validated['wallpaper'] === 'custom' ? $oldPath : null),
            'blur' => (int) $validated['blur'],
            'overlay' => (int) $validated['overlay'],
        ]);

        try {
            $preference->user()->associate($request->user());
            $preference->save();
        } catch (QueryException $exception) {
            if ($newPath !== null && ! Storage::disk('local')->delete($newPath)) {
                Log::warning('Failed to clean up an unreferenced wallpaper upload.', [
                    'user_id' => $request->user()->getKey(),
                ]);
            }

            throw $exception;
        }

        if ($oldPath !== null
            && $oldPath !== $preference->wallpaper_upload_path
            && Storage::disk('local')->exists($oldPath)
            && ! Storage::disk('local')->delete($oldPath)) {
            Log::warning('Failed to delete a replaced wallpaper upload.', [
                'user_id' => $request->user()->getKey(),
            ]);
        }

        return redirect()->route('preferences.index')->with('status', 'Preferensi berhasil disimpan.');
    }

    public function wallpaper(Request $request): BinaryFileResponse
    {
        $path = $request->user()->preference?->wallpaper_upload_path;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));

        abort_unless(
            is_string($root)
                && is_string($file)
                && str_starts_with($file, $root.DIRECTORY_SEPARATOR)
                && is_file($file),
            404,
        );

        $mime = mime_content_type($file);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 404);

        $response = response()->file($file, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function updateSidebar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sidebar_title' => ['nullable', 'string', 'max:60'],
            'sidebar_description' => ['nullable', 'string', 'max:240'],
        ]);

        $request->user()->preference()->updateOrCreate([], [
            'sidebar_title' => $validated['sidebar_title'] ?? null,
            'sidebar_description' => $validated['sidebar_description'] ?? null,
        ]);

        return redirect()->route('preferences.index')->with('status', 'Teks sidebar berhasil disimpan.');
    }
}
