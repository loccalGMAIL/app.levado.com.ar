<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crea la lista "General" por tenant y copia los selling_price existentes.
     */
    public function up(): void
    {
        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $priceListId = DB::table('price_lists')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'General',
                'adjustment_pct' => null,
                'is_default' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $recipes = DB::table('recipes')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('selling_price')
                ->get(['id', 'selling_price']);

            foreach ($recipes as $recipe) {
                DB::table('recipe_prices')->insert([
                    'tenant_id' => $tenantId,
                    'price_list_id' => $priceListId,
                    'recipe_id' => $recipe->id,
                    'price' => $recipe->selling_price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('recipe_price_logs')->insert([
                    'recipe_id' => $recipe->id,
                    'price_list_id' => $priceListId,
                    'price' => $recipe->selling_price,
                    'recorded_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $defaultListIds = DB::table('price_lists')->where('is_default', true)->pluck('id');

        DB::table('recipe_price_logs')->whereIn('price_list_id', $defaultListIds)->delete();
        DB::table('recipe_prices')->whereIn('price_list_id', $defaultListIds)->delete();
        DB::table('price_lists')->whereIn('id', $defaultListIds)->delete();
    }
};
