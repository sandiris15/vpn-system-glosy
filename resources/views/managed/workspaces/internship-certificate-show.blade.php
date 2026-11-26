@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Sertifikat PKL/MAGANG')
@section('breadcrumb', 'Workspaces / Sertifikat PKL/MAGANG')
@section('mobile-heading', 'Sertifikat PKL/MAGANG')

@section('content')
    <a href="{{ route('workspaces.internship-certificates.index') }}" class="text-sm text-slate-400 hover:text-white">← Kembali ke sertifikat</a>
    <h1 class="mt-4 text-3xl font-semibold text-white">Sertifikat {{ $certificate->name }}</h1>
    <p class="mt-2 text-sm text-slate-400">{{ $certificate->number }}</p>
    <div class="mt-5" x-data="internshipCertificate(@js([
        'name' => $certificate->name,
        'number' => $certificate->number,
        'program' => $certificate->program,
        'location' => $certificate->location,
        'institution' => $certificate->institution,
        'birthDate' => $certificate->birth_date?->locale('id')->translatedFormat('d F Y'),
        'period' => $certificate->start_date->locale('id')->translatedFormat('d F Y').' – '.$certificate->end_date->locale('id')->translatedFormat('d F Y'),
        'issued' => $certificate->issued_date->locale('id')->translatedFormat('d F Y'),
        'logo' => route('workspaces.internship-certificates.asset', 'logo'),
        'signature' => route('workspaces.internship-certificates.asset', 'signature'),
    ]))">
        <p x-show="error" x-text="error" class="mb-4 text-sm text-rose-200" role="alert"></p>
        <canvas x-ref="canvas" width="1800" height="1200" class="h-auto w-full rounded-lg bg-white" aria-label="Pratinjau sertifikat"></canvas>
        <button type="button" class="button-primary mt-5" @click="download()" :disabled="!ready">Unduh PNG</button>
    </div>
@endsection
