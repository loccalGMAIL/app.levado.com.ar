<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices identificados en la auditoría de acceso a datos (04/08/2026, §4).
     * Reejecutable: cada bloque verifica con Schema::getIndexes() antes de crear,
     * siguiendo el patrón de 2026_06_15_165805_add_missing_indexes.php.
     */
    public function up(): void
    {
        // I1 — recipes: dashboard, /recipes y matriz filtran tenant_id+active y ordenan por name.
        if (! $this->hasIndex('recipes', 'recipes_tenant_id_active_name_index')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->index(['tenant_id', 'active', 'name']);
            });
        }

        // I2 — recipe_prices: la subconsulta correlacionada del dashboard busca por recipe_id primero;
        // el único existente es (price_list_id, recipe_id), orden inverso, inservible como prefijo.
        if (! $this->hasIndex('recipe_prices', 'recipe_prices_recipe_id_price_list_id_index')) {
            Schema::table('recipe_prices', function (Blueprint $table) {
                $table->index(['recipe_id', 'price_list_id']);
            });
        }

        // I3 — ingredient_price_logs: max(recorded_at) agrupado por ingredient_id en syncStaleCost.
        if (! $this->hasIndex('ingredient_price_logs', 'ingredient_price_logs_ingredient_id_recorded_at_index')) {
            Schema::table('ingredient_price_logs', function (Blueprint $table) {
                $table->index(['ingredient_id', 'recorded_at']);
            });
        }

        // I4 — packaging_price_logs: simétrico a I3, por si se extiende la alerta a descartables.
        if (! $this->hasIndex('packaging_price_logs', 'packaging_price_logs_packaging_id_recorded_at_index')) {
            Schema::table('packaging_price_logs', function (Blueprint $table) {
                $table->index(['packaging_id', 'recorded_at']);
            });
        }

        // I5 — purchase_lines: predicado "sin resolver" de syncUnappliedPurchases y los withCount
        // condicionales de PurchaseController::index.
        if (! $this->hasIndex('purchase_lines', 'purchase_lines_purchase_id_cost_applied_at_excluded_at_index')) {
            Schema::table('purchase_lines', function (Blueprint $table) {
                $table->index(['purchase_id', 'cost_applied_at', 'excluded_at']);
            });
        }

        // I6 — packagings: ingredients ya tiene (tenant_id, active); packagings quedó asimétrico.
        if (! $this->hasIndex('packagings', 'packagings_tenant_id_active_index')) {
            Schema::table('packagings', function (Blueprint $table) {
                $table->index(['tenant_id', 'active']);
            });
        }

        // I7 — stock_levels: el único existente arranca por tenant_id+stockable_type, sin servir de
        // prefijo para los filtros por sucursal de StockController/IngredientController/PackagingController.
        if (! $this->hasIndex('stock_levels', 'stock_levels_tenant_id_location_id_stockable_type_index')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->index(['tenant_id', 'location_id', 'stockable_type']);
            });
        }

        // I8 — stock_movements: activePurchaseEntryFor() filtra por los tres y ordena por id desc;
        // el índice existente es solo (reference_type, reference_id).
        if (! $this->hasIndex('stock_movements', 'stock_movements_reference_type_reference_id_type_index')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id', 'type']);
            });
        }

        // I9 — notifications: reemplaza al índice de dedupe existente, agregando resolved_at para
        // no tener que ir a la fila a descartar las ya resueltas.
        if ($this->hasIndex('notifications', 'notifications_dedupe_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_dedupe_index');
            });
        }
        if (! $this->hasIndex('notifications', 'notifications_dedupe_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['tenant_id', 'dedupe_key', 'resolved_at'], 'notifications_dedupe_index');
            });
        }

        // I10 — locations: defaultLocation() filtra por is_default; hoy solo hay index('tenant_id').
        if (! $this->hasIndex('locations', 'locations_tenant_id_is_default_index')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->index(['tenant_id', 'is_default']);
            });
        }

        // I11 — variable_expenses: filtro combinado de categoría + período. Solo aprovechable junto
        // con el fix de Q14 (whereDate() inutilizaba cualquier índice sobre expense_date).
        if (! $this->hasIndex('variable_expenses', 'variable_expenses_tenant_category_date_index')) {
            Schema::table('variable_expenses', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'variable_expense_category_id', 'expense_date'],
                    'variable_expenses_tenant_category_date_index',
                );
            });
        }

        // I12 — tenant_users: isSuperAdmin() y roleInTenant() filtran por user_id; los índices
        // declarados arrancan por tenant_id. Compuesto y cubridor: resuelve sin ir a la fila.
        if (! $this->hasIndex('tenant_users', 'tenant_users_user_id_active_role_index')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->index(['user_id', 'active', 'role']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'active', 'name']);
        });

        Schema::table('recipe_prices', function (Blueprint $table) {
            $table->dropIndex(['recipe_id', 'price_list_id']);
        });

        Schema::table('ingredient_price_logs', function (Blueprint $table) {
            $table->dropIndex(['ingredient_id', 'recorded_at']);
        });

        Schema::table('packaging_price_logs', function (Blueprint $table) {
            $table->dropIndex(['packaging_id', 'recorded_at']);
        });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropIndex(['purchase_id', 'cost_applied_at', 'excluded_at']);
        });

        Schema::table('packagings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'active']);
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'location_id', 'stockable_type']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id', 'type']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_dedupe_index');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['tenant_id', 'dedupe_key'], 'notifications_dedupe_index');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_default']);
        });

        Schema::table('variable_expenses', function (Blueprint $table) {
            $table->dropIndex('variable_expenses_tenant_category_date_index');
        });

        Schema::table('tenant_users', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'active', 'role']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains('name', $indexName);
    }
};
