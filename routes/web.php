<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\MailPreviewController;
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
use App\Http\Controllers\PackagingCostController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseScanController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipePriceController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\VariableExpenseCategoryController;
use App\Http\Controllers\VariableExpenseController;
use App\Http\Controllers\VariableExpenseScanController;
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
    Route::get('variable-expenses', [VariableExpenseController::class, 'index'])->name('variable-expenses.index');
    Route::get('variable-expenses/{variableExpense}/receipt', [VariableExpenseController::class, 'receipt'])->name('variable-expenses.receipt');
    Route::get('labor-types', [LaborTypeController::class, 'index'])->name('labor-types.index');
    Route::get('price-lists', [PriceListController::class, 'index'])->name('price-lists.index');
    Route::get('price-lists/matrix', [PriceListController::class, 'matrix'])->name('price-lists.matrix');

    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');

    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    // La pantalla de escaneo se registra antes que purchases/{purchase} para que
    // "scan" no se interprete como un id de compra (route-model binding).
    Route::get('purchases/check-duplicate', [PurchaseController::class, 'checkDuplicate'])->name('purchases.check-duplicate');
    Route::get('purchases/scan', [PurchaseScanController::class, 'create'])
        ->middleware('role:super_admin,owner,admin')
        ->name('purchases.scan.create');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('purchases/{purchase}/match', [PurchaseController::class, 'match'])->name('purchases.match');
    Route::get('purchases/{purchase}/invoice', [PurchaseController::class, 'invoiceImage'])->name('purchases.invoice');

    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/{type}/{id}', [StockController::class, 'show'])
        ->whereIn('type', ['ingredient', 'packaging'])
        ->whereNumber('id')
        ->name('stock.show');
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
    Route::patch('packaging/{packaging}/cost', [PackagingCostController::class, 'update'])->name('packaging.cost.update');
    Route::patch('packaging/{packaging}/toggle-active', [PackagingController::class, 'toggleActive'])->name('packaging.toggle-active');

    Route::post('fixed-costs', [FixedCostController::class, 'store'])->name('fixed-costs.store');
    Route::put('fixed-costs/{fixedCost}', [FixedCostController::class, 'update'])->name('fixed-costs.update');
    Route::patch('fixed-costs/{fixedCost}/toggle-active', [FixedCostController::class, 'toggleActive'])->name('fixed-costs.toggle-active');

    // Antes de variable-expenses/{variableExpense} para que "scan" no se
    // interprete como un id de gasto (route-model binding). Mismo throttle que
    // el escaneo de facturas y por lo mismo: cada lectura es una llamada paga a
    // la API de Anthropic que además bloquea un worker hasta 60 s.
    Route::post('variable-expenses/scan', [VariableExpenseScanController::class, 'scan'])
        ->middleware('throttle:10,1')
        ->name('variable-expenses.scan');
    Route::post('variable-expenses', [VariableExpenseController::class, 'store'])->name('variable-expenses.store');
    Route::put('variable-expenses/{variableExpense}', [VariableExpenseController::class, 'update'])->name('variable-expenses.update');
    Route::delete('variable-expenses/{variableExpense}', [VariableExpenseController::class, 'destroy'])->name('variable-expenses.destroy');

    Route::post('labor-types', [LaborTypeController::class, 'store'])->name('labor-types.store');
    Route::put('labor-types/{laborType}', [LaborTypeController::class, 'update'])->name('labor-types.update');
    Route::patch('labor-types/{laborType}/toggle-active', [LaborTypeController::class, 'toggleActive'])->name('labor-types.toggle-active');

    Route::post('price-lists', [PriceListController::class, 'store'])->name('price-lists.store');
    Route::put('price-lists/{priceList}', [PriceListController::class, 'update'])->name('price-lists.update');
    Route::patch('price-lists/{priceList}/toggle-active', [PriceListController::class, 'toggleActive'])->name('price-lists.toggle-active');
    Route::post('price-lists/apply-all-suggestions', [PriceListController::class, 'applyAllSuggestions'])->name('price-lists.apply-all-suggestions');
    Route::post('price-lists/{priceList}/apply-suggestions', [PriceListController::class, 'applySuggestions'])->name('price-lists.apply-suggestions');

    Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::post('recipes/{recipe}/copy', [RecipeController::class, 'copy'])->name('recipes.copy');
    Route::put('recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::patch('recipes/{recipe}/prices/{priceList}', [RecipePriceController::class, 'update'])->name('recipes.prices.update');
    Route::patch('recipes/{recipe}/toggle-active', [RecipeController::class, 'toggleActive'])->name('recipes.toggle-active');

    // Los parámetros de línea ({ingredientLine}, {line}, etc.) usan scoped bindings:
    // Laravel resuelve la línea DENTRO de la relación del padre (404 si no pertenece),
    // sin necesidad de chequeos manuales de recipe_id/purchase_id en el controller.
    Route::post('recipes/{recipe}/ingredient-lines', [RecipeController::class, 'storeIngredientLine'])->name('recipes.ingredient-lines.store');
    Route::patch('recipes/{recipe}/ingredient-lines/{ingredientLine}', [RecipeController::class, 'updateIngredientLine'])->scopeBindings()->name('recipes.ingredient-lines.update');
    Route::delete('recipes/{recipe}/ingredient-lines/{ingredientLine}', [RecipeController::class, 'destroyIngredientLine'])->scopeBindings()->name('recipes.ingredient-lines.destroy');

    Route::post('recipes/{recipe}/packaging-lines', [RecipeController::class, 'storePackagingLine'])->name('recipes.packaging-lines.store');
    Route::patch('recipes/{recipe}/packaging-lines/{packagingLine}', [RecipeController::class, 'updatePackagingLine'])->scopeBindings()->name('recipes.packaging-lines.update');
    Route::delete('recipes/{recipe}/packaging-lines/{packagingLine}', [RecipeController::class, 'destroyPackagingLine'])->scopeBindings()->name('recipes.packaging-lines.destroy');

    Route::post('recipes/{recipe}/labor-lines', [RecipeController::class, 'storeLaborLine'])->name('recipes.labor-lines.store');
    Route::patch('recipes/{recipe}/labor-lines/{laborLine}', [RecipeController::class, 'updateLaborLine'])->scopeBindings()->name('recipes.labor-lines.update');
    Route::delete('recipes/{recipe}/labor-lines/{laborLine}', [RecipeController::class, 'destroyLaborLine'])->scopeBindings()->name('recipes.labor-lines.destroy');

    Route::post('recipes/{recipe}/subrecipe-lines', [RecipeController::class, 'storeSubrecipeLine'])->name('recipes.subrecipe-lines.store');
    Route::patch('recipes/{recipe}/subrecipe-lines/{subrecipeLine}', [RecipeController::class, 'updateSubrecipeLine'])->scopeBindings()->name('recipes.subrecipe-lines.update');
    Route::delete('recipes/{recipe}/subrecipe-lines/{subrecipeLine}', [RecipeController::class, 'destroySubrecipeLine'])->scopeBindings()->name('recipes.subrecipe-lines.destroy');

    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::patch('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    // Cada escaneo dispara una llamada a la API de Anthropic (costo directo):
    // el throttle acota abuso accidental o malicioso por usuario.
    Route::post('purchases/scan', [PurchaseScanController::class, 'scan'])
        ->middleware('throttle:10,1')
        ->name('purchases.scan');
    Route::post('purchases/scan/confirm', [PurchaseScanController::class, 'store'])->name('purchases.scan.store');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    Route::post('purchases/{purchase}/lines', [PurchaseController::class, 'storeLine'])->name('purchases.lines.store');
    Route::patch('purchases/{purchase}/lines/{line}', [PurchaseController::class, 'updateLine'])->scopeBindings()->name('purchases.lines.update');
    Route::patch('purchases/{purchase}/lines/{line}/price', [PurchaseController::class, 'updateLinePrice'])->scopeBindings()->name('purchases.lines.price.update');
    Route::delete('purchases/{purchase}/lines/{line}', [PurchaseController::class, 'destroyLine'])->scopeBindings()->name('purchases.lines.destroy');
    Route::post('purchases/{purchase}/lines/{line}/match', [PurchaseController::class, 'matchLine'])->scopeBindings()->name('purchases.lines.match');
    Route::post('purchases/{purchase}/apply-suggestions', [PurchaseController::class, 'applyLineSuggestions'])->name('purchases.apply-suggestions');

    Route::post('stock/{type}/{id}/adjustments', [StockController::class, 'storeAdjustment'])
        ->whereIn('type', ['ingredient', 'packaging'])->whereNumber('id')->name('stock.adjustments.store');
    Route::post('stock/{type}/{id}/counts', [StockController::class, 'storeCount'])
        ->whereIn('type', ['ingredient', 'packaging'])->whereNumber('id')->name('stock.counts.store');
    Route::patch('stock/{type}/{id}/min', [StockController::class, 'updateMin'])
        ->whereIn('type', ['ingredient', 'packaging'])->whereNumber('id')->name('stock.min.update');
    Route::patch('stock/{type}/{id}/level', [StockController::class, 'updateLevel'])
        ->whereIn('type', ['ingredient', 'packaging'])->whereNumber('id')->name('stock.level.update');

    Route::post('fixed-cost-categories', [FixedCostCategoryController::class, 'store'])->name('fixed-cost-categories.store');
    Route::put('fixed-cost-categories/{fixedCostCategory}', [FixedCostCategoryController::class, 'update'])->name('fixed-cost-categories.update');
    Route::delete('fixed-cost-categories/{fixedCostCategory}', [FixedCostCategoryController::class, 'destroy'])->name('fixed-cost-categories.destroy');

    Route::post('variable-expense-categories', [VariableExpenseCategoryController::class, 'store'])->name('variable-expense-categories.store');
    Route::put('variable-expense-categories/{variableExpenseCategory}', [VariableExpenseCategoryController::class, 'update'])->name('variable-expense-categories.update');
    Route::delete('variable-expense-categories/{variableExpenseCategory}', [VariableExpenseCategoryController::class, 'destroy'])->name('variable-expense-categories.destroy');
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

    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('impersonate/{tenant}', [ImpersonationController::class, 'start'])->name('impersonate.start');

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('users/{user}/send-password-reset', [AdminUserController::class, 'sendPasswordReset'])->name('users.send-password-reset');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('mails', [MailPreviewController::class, 'index'])->name('mails.index');
    Route::patch('mails/{type}', [MailPreviewController::class, 'update'])->name('mails.update');
    Route::get('mails/preview/team-invitation', [MailPreviewController::class, 'teamInvitation'])->name('mails.preview.team-invitation');
    Route::get('mails/preview/welcome', [MailPreviewController::class, 'welcome'])->name('mails.preview.welcome');
});

require __DIR__.'/auth.php';
