<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'tenant'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Aceptar invitación (sin auth — el usuario puede no tener cuenta aún)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [InvitationController::class, 'accept']);

// Mi negocio (solo owner y super_admin pueden editar)
Route::middleware(['auth', 'verified', 'tenant', 'role:super_admin,owner'])->group(function () {
    Route::get('/business', [BusinessController::class, 'edit'])->name('business.edit');
    Route::patch('/business', [BusinessController::class, 'update'])->name('business.update');
});

// Mi equipo (requiere auth + tenant resuelto + rol manage-team)
Route::middleware(['auth', 'verified', 'tenant', 'role:super_admin,owner,admin'])->group(function () {
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team/invitations', [InvitationController::class, 'store'])->name('team.invitations.store');
    Route::delete('/team/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('team.invitations.destroy');
    Route::patch('/team/members/{tenantUser}/role', [TeamController::class, 'updateRole'])->name('team.members.role');
    Route::patch('/team/members/{tenantUser}/deactivate', [TeamController::class, 'deactivate'])->name('team.members.deactivate');
    Route::patch('/team/members/{tenantUser}/activate', [TeamController::class, 'activate'])->name('team.members.activate');
});

require __DIR__.'/auth.php';
