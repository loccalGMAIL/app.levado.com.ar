<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // recipe_ingredient_lines.ingredient_id
        if (! $this->hasIndex('recipe_ingredient_lines', 'recipe_ingredient_lines_ingredient_id_index')) {
            Schema::table('recipe_ingredient_lines', function (Blueprint $table) {
                $table->index('ingredient_id');
            });
        }

        // recipe_packaging_lines.packaging_id
        if (! $this->hasIndex('recipe_packaging_lines', 'recipe_packaging_lines_packaging_id_index')) {
            Schema::table('recipe_packaging_lines', function (Blueprint $table) {
                $table->index('packaging_id');
            });
        }

        // recipe_labor_lines.labor_type_id
        if (! $this->hasIndex('recipe_labor_lines', 'recipe_labor_lines_labor_type_id_index')) {
            Schema::table('recipe_labor_lines', function (Blueprint $table) {
                $table->index('labor_type_id');
            });
        }

        // ingredients.supplier_id
        if (! $this->hasIndex('ingredients', 'ingredients_supplier_id_index')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->index('supplier_id');
            });
        }

        // packagings.supplier_id
        if (! $this->hasIndex('packagings', 'packagings_supplier_id_index')) {
            Schema::table('packagings', function (Blueprint $table) {
                $table->index('supplier_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('recipe_ingredient_lines', function (Blueprint $table) {
            $table->dropIndex(['ingredient_id']);
        });

        Schema::table('recipe_packaging_lines', function (Blueprint $table) {
            $table->dropIndex(['packaging_id']);
        });

        Schema::table('recipe_labor_lines', function (Blueprint $table) {
            $table->dropIndex(['labor_type_id']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
        });

        Schema::table('packagings', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains('name', $indexName);
    }
};
