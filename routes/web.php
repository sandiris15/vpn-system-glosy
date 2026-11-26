<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Managed\PreferencesController;
use App\Http\Controllers\Mandiri\MandiriController;
use App\Http\Controllers\Managed\DashboardController;
use App\Http\Controllers\Managed\ForumController;
use App\Http\Controllers\Managed\InternshipCertificateController;
use App\Http\Controllers\Managed\TopologyController;
use App\Http\Controllers\Managed\VpnAccountController;
use App\Http\Controllers\Managed\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/mandiri/connection', [MandiriController::class, 'connection'])->name('mandiri.connection');
Route::post('/mandiri/connection', [MandiriController::class, 'connect'])
    ->middleware('throttle:5,1')
    ->name('mandiri.connect');
Route::middleware('mandiri.session')->group(function (): void {
    Route::get('/mandiri/provision', [MandiriController::class, 'provisionForm'])->name('mandiri.provision.create');
    Route::post('/mandiri/provision', [MandiriController::class, 'provision'])
        ->middleware('throttle:10,1')
        ->name('mandiri.provision.store');
    Route::post('/mandiri/logout', [MandiriController::class, 'logout'])->name('mandiri.logout');
});

Route::middleware(['auth', 'managed.server'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/preferences', [PreferencesController::class, 'index'])->name('preferences.index');
    Route::put('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    Route::put('/preferences/sidebar', [PreferencesController::class, 'updateSidebar'])->name('preferences.sidebar.update');
    Route::get('/preferences/wallpaper', [PreferencesController::class, 'wallpaper'])->name('preferences.wallpaper');
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/sertifikat-magang', [InternshipCertificateController::class, 'index'])->name('workspaces.internship-certificates.index');
    Route::post('/workspaces/sertifikat-magang', [InternshipCertificateController::class, 'store'])->name('workspaces.internship-certificates.store');
    Route::get('/workspaces/sertifikat-magang/assets/{asset}', [InternshipCertificateController::class, 'asset'])->name('workspaces.internship-certificates.asset');
    Route::get('/workspaces/sertifikat-magang/{certificate}', [InternshipCertificateController::class, 'show'])->name('workspaces.internship-certificates.show');
    Route::get('/workspaces/hotspot-template', [WorkspaceController::class, 'hotspotTemplate'])->name('workspaces.hotspot-template');
    Route::get('/workspaces/topology', [TopologyController::class, 'index'])->name('workspaces.topology.index');
    Route::post('/workspaces/topology', [TopologyController::class, 'store'])->name('workspaces.topology.store');
    Route::get('/workspaces/topology/{topology}', [TopologyController::class, 'show'])->name('workspaces.topology.show');
    Route::post('/workspaces/topology/{topology}/unlock', [TopologyController::class, 'unlock'])->middleware('throttle:5,1')->name('workspaces.topology.unlock');
    Route::put('/workspaces/topology/{topology}', [TopologyController::class, 'update'])->name('workspaces.topology.update');
    Route::get('/workspaces/isp-failover', [WorkspaceController::class, 'ispFailover'])->name('workspaces.isp-failover');
    Route::post('/workspaces/isp-failover/script', [WorkspaceController::class, 'generateIspFailoverScript'])->name('workspaces.isp-failover.generate');
    Route::get('/workspaces/ethernet-scheduler', [WorkspaceController::class, 'ethernetScheduler'])->name('workspaces.ethernet-scheduler');
    Route::post('/workspaces/ethernet-scheduler/script', [WorkspaceController::class, 'generateEthernetSchedule'])->name('workspaces.ethernet-scheduler.generate');
    Route::get('/workspaces/vlan-scheduler', [WorkspaceController::class, 'vlanScheduler'])->name('workspaces.vlan-scheduler');
    Route::post('/workspaces/vlan-scheduler/script', [WorkspaceController::class, 'generateVlanSchedule'])->name('workspaces.vlan-scheduler.generate');
    Route::get('/workspaces/script-library', [WorkspaceController::class, 'scriptLibrary'])->name('workspaces.script-library');
    Route::post('/workspaces/script-library/generate', [WorkspaceController::class, 'generateLibraryScript'])->name('workspaces.script-library.generate');
    Route::get('/workspaces/mikrotik-setup', [WorkspaceController::class, 'mikrotikSetup'])->name('workspaces.mikrotik-setup');
    Route::post('/workspaces/mikrotik-setup/script', [WorkspaceController::class, 'generateMikrotikSetup'])->name('workspaces.mikrotik-setup.generate');
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'planned'])->name('workspaces.planned');
    Route::get('/forum', [ForumController::class, 'index'])->name('forum.index');
    Route::post('/forum/topics', [ForumController::class, 'store'])->name('forum.topics.store');
    Route::get('/forum/topics/{topic}', [ForumController::class, 'show'])->name('forum.show');
    Route::post('/forum/topics/{topic}/replies', [ForumController::class, 'reply'])->name('forum.replies.store');
    Route::delete('/forum/topics/{topic}', [ForumController::class, 'destroyTopic'])->name('forum.topics.destroy');
    Route::delete('/forum/replies/{reply}', [ForumController::class, 'destroyReply'])->name('forum.replies.destroy');
    Route::get('/vpn-accounts', [VpnAccountController::class, 'index'])->name('vpn-accounts.index');
    Route::get('/vpn-accounts/create', [VpnAccountController::class, 'create'])->name('vpn-accounts.create');
    Route::post('/vpn-accounts', [VpnAccountController::class, 'store'])->name('vpn-accounts.store');
    Route::post('/vpn-accounts/{vpnAccount}/script', [VpnAccountController::class, 'script'])->name('vpn-accounts.script');
    Route::get('/vpn-accounts/{vpnAccount}/edit', [VpnAccountController::class, 'edit'])->name('vpn-accounts.edit');
    Route::put('/vpn-accounts/{vpnAccount}', [VpnAccountController::class, 'update'])->name('vpn-accounts.update');
    Route::delete('/vpn-accounts/{vpnAccount}', [VpnAccountController::class, 'destroy'])->name('vpn-accounts.destroy');
    Route::get('/vpn-accounts/{vpnAccount}', [VpnAccountController::class, 'show'])->name('vpn-accounts.show');
});
