<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MailPreviewController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedCostCategoryController;
use App\Http\Controllers\FixedCostController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LaborTypeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PackagingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'tenant'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Aceptar invitación (sin auth — el usuario puede no tener cuenta aún)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [InvitationController::class, 'accept']);

// Costos — lectura (todos los roles con tenant)
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('packaging', [PackagingController::class, 'index'])->name('packaging.index');
    Route::get('fixed-costs', [FixedCostController::class, 'index'])->name('fixed-costs.index');
    Route::get('labor-types', [LaborTypeController::class, 'index'])->name('labor-types.index');

    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
});

// Costos — escritura (owner y admin)
Route::middleware(['auth', 'verified', 'tenant', 'role:super_admin,owner,admin'])->group(function () {
    Route::post('ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
    Route::put('ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
    Route::patch('ingredients/{ingredient}/toggle-active', [IngredientController::class, 'toggleActive'])->name('ingredients.toggle-active');

    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::patch('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])->name('suppliers.toggle-active');

    Route::post('packaging', [PackagingController::class, 'store'])->name('packaging.store');
    Route::put('packaging/{packaging}', [PackagingController::class, 'update'])->name('packaging.update');
    Route::patch('packaging/{packaging}/toggle-active', [PackagingController::class, 'toggleActive'])->name('packaging.toggle-active');

    Route::post('fixed-costs', [FixedCostController::class, 'store'])->name('fixed-costs.store');
    Route::put('fixed-costs/{fixedCost}', [FixedCostController::class, 'update'])->name('fixed-costs.update');
    Route::patch('fixed-costs/{fixedCost}/toggle-active', [FixedCostController::class, 'toggleActive'])->name('fixed-costs.toggle-active');

    Route::post('labor-types', [LaborTypeController::class, 'store'])->name('labor-types.store');
    Route::put('labor-types/{laborType}', [LaborTypeController::class, 'update'])->name('labor-types.update');
    Route::patch('labor-types/{laborType}/toggle-active', [LaborTypeController::class, 'toggleActive'])->name('labor-types.toggle-active');

    Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::put('recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::patch('recipes/{recipe}/toggle-active', [RecipeController::class, 'toggleActive'])->name('recipes.toggle-active');

    Route::post('recipes/{recipe}/ingredient-lines', [RecipeController::class, 'storeIngredientLine'])->name('recipes.ingredient-lines.store');
    Route::patch('recipes/{recipe}/ingredient-lines/{line}', [RecipeController::class, 'updateIngredientLine'])->name('recipes.ingredient-lines.update');
    Route::delete('recipes/{recipe}/ingredient-lines/{line}', [RecipeController::class, 'destroyIngredientLine'])->name('recipes.ingredient-lines.destroy');

    Route::post('recipes/{recipe}/packaging-lines', [RecipeController::class, 'storePackagingLine'])->name('recipes.packaging-lines.store');
    Route::patch('recipes/{recipe}/packaging-lines/{line}', [RecipeController::class, 'updatePackagingLine'])->name('recipes.packaging-lines.update');
    Route::delete('recipes/{recipe}/packaging-lines/{line}', [RecipeController::class, 'destroyPackagingLine'])->name('recipes.packaging-lines.destroy');

    Route::post('recipes/{recipe}/labor-lines', [RecipeController::class, 'storeLaborLine'])->name('recipes.labor-lines.store');
    Route::patch('recipes/{recipe}/labor-lines/{line}', [RecipeController::class, 'updateLaborLine'])->name('recipes.labor-lines.update');
    Route::delete('recipes/{recipe}/labor-lines/{line}', [RecipeController::class, 'destroyLaborLine'])->name('recipes.labor-lines.destroy');

    Route::post('fixed-cost-categories', [FixedCostCategoryController::class, 'store'])->name('fixed-cost-categories.store');
    Route::put('fixed-cost-categories/{fixedCostCategory}', [FixedCostCategoryController::class, 'update'])->name('fixed-cost-categories.update');
    Route::delete('fixed-cost-categories/{fixedCostCategory}', [FixedCostCategoryController::class, 'destroy'])->name('fixed-cost-categories.destroy');
});

// Mi negocio y sucursales (solo owner y super_admin)
Route::middleware(['auth', 'verified', 'tenant', 'role:super_admin,owner'])->group(function () {
    Route::get('/business', [BusinessController::class, 'edit'])->name('business.edit');
    Route::patch('/business', [BusinessController::class, 'update'])->name('business.update');

    Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
    Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
    Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
    Route::patch('locations/{location}/toggle-active', [LocationController::class, 'toggleActive'])->name('locations.toggle-active');
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

// Backoffice de administración (solo super admins)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'super-admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('tenants', AdminTenantController::class)->except(['destroy']);
    Route::patch('tenants/{tenant}/toggle-active', [AdminTenantController::class, 'toggleActive'])->name('tenants.toggle-active');

    Route::post('impersonate/{tenant}', [ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('mails', [MailPreviewController::class, 'index'])->name('mails.index');
    Route::get('mails/preview/team-invitation', [MailPreviewController::class, 'teamInvitation'])->name('mails.preview.team-invitation');
    Route::get('mails/preview/welcome', [MailPreviewController::class, 'welcome'])->name('mails.preview.welcome');
});

require __DIR__.'/auth.php';
