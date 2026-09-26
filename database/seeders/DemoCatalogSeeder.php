<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductPriceWriter;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos demo para el módulo de Artículos (Productos) + Producción, sobre el
 * tenant "Panadería Demo". Idempotente: se puede correr varias veces.
 *
 * Correr con: php artisan db:seed --class=DemoCatalogSeeder
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['name' => 'Panadería Demo'],
            ['country' => 'AR', 'currency' => 'ARS', 'productive_hours_month' => 160, 'active' => true],
        );

        $owner = User::firstOrCreate(
            ['email' => 'owner@demo.com'],
            ['name' => 'Owner Demo', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $tenant->tenantUsers()->firstOrCreate(
            ['user_id' => $owner->id],
            ['role' => TenantUserRole::Owner->value, 'active' => true],
        );

        $supplier = $tenant->suppliers()->firstOrCreate(['name' => 'Distribuidora Demo'], ['active' => true]);

        // --- Productos de reventa ---
        $coca = $tenant->products()->firstOrCreate(
            ['name' => 'Coca-Cola 500ml'],
            ['type' => ProductType::Resale->value, 'unit' => Unit::Unidad->value, 'cost_per_unit' => 800, 'barcode' => '7790895000997', 'active' => true],
        );
        $agua = $tenant->products()->firstOrCreate(
            ['name' => 'Agua Mineral 500ml'],
            ['type' => ProductType::Resale->value, 'unit' => Unit::Unidad->value, 'cost_per_unit' => 400, 'barcode' => '7790895111000', 'active' => true],
        );

        // --- Producto elaborado (ligado a una receta no-semi) ---
        $recipe = $tenant->recipes()->firstOrCreate(
            ['name' => 'Facturas de manteca'],
            ['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value, 'active' => true, 'is_semi_elaborate' => false],
        );
        $tenant->products()->firstOrCreate(
            ['name' => 'Docena de facturas'],
            ['type' => ProductType::Manufactured->value, 'recipe_id' => $recipe->id, 'unit' => Unit::Unidad->value, 'active' => true],
        );

        // --- Precios de venta de reventa (para ver el margen en la matriz) ---
        $list = $tenant->defaultPriceList();
        $writer = app(ProductPriceWriter::class);
        $writer->set($coca, $list, 1200);
        $writer->set($agua, $list, 700);

        // --- Stock inicial de un producto de reventa ---
        if ($coca->stockLevels()->doesntExist()) {
            app(StockService::class)->registerAdjustment($coca, $tenant->defaultLocation(), 24, 'Carga inicial demo', $owner);
        }

        // --- Compra con un renglón pendiente (para ver el match con productos) ---
        $purchase = $tenant->purchases()->firstOrCreate(
            ['supplier_id' => $supplier->id, 'invoice_number' => '0001-DEMO01'],
            ['invoice_date' => now()->format('Y-m-d')],
        );
        $purchase->lines()->firstOrCreate(
            ['raw_name' => 'COCA COLA 500ML X12'],
            ['quantity_purchased' => 12, 'purchase_unit' => Unit::Unidad->value, 'unit_price' => 800, 'iva_rate' => 0.21, 'subtotal' => 9600],
        );

        $this->command->info('Demo Artículos cargada en "Panadería Demo" — login: owner@demo.com / password');
    }
}
