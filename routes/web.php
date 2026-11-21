<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Managed\PreferencesController;
use App\Http\Controllers\Mandiri\MandiriController;
use App\Http\Controllers\Managed\DashboardController;
use App\Http\Controllers\Managed\ForumController;
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
    Route::get('/preferences/wallpaper', [PreferencesController::class, 'wallpaper'])->name('preferences.wallpaper');
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/hotspot-template', [WorkspaceController::class, 'hotspotTemplate'])->name('workspaces.hotspot-template');
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
