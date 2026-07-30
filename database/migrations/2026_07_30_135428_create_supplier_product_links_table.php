<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memoria de vinculación: qué insumo/descartable significa cada texto de
     * renglón para un proveedor dado. Hasta acá el vínculo vivía sólo en
     * purchase_lines, así que la corrección humana se perdía y cada factura
     * volvía a depender de lo que adivinara la IA.
     *
     * Es un caché de decisiones, no un registro histórico: olvidar es una
     * operación legítima del usuario, así que esta tabla sí usa borrado físico.
     */
    public function up(): void
    {
        Schema::create('supplier_product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            // Texto del renglón plegado por ProductLinkMemory::fold() — la clave de búsqueda.
            $table->string('raw_name_normalized');
            // El último texto tal cual vino, sólo para leer y depurar.
            $table->string('raw_name_sample');

            $table->string('purchaseable_type', 20); // App\Enums\CatalogItemType
            // Sin FK: apunta a dos tablas distintas, igual que purchase_lines.
            $table->unsignedBigInteger('purchaseable_id');

            // Divisor para unidades incompatibles (u → kg), ya expresado en la
            // unidad del catálogo. El costo unitario NO se guarda: ese cambia.
            $table->decimal('pkg_qty', 14, 4)->nullable();

            $table->unsignedInteger('times_confirmed')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'supplier_id', 'raw_name_normalized'],
                'supplier_product_links_key_unique',
            );
            // Para poder limpiar los vínculos de un ítem dado de baja del catálogo.
            $table->index(['purchaseable_type', 'purchaseable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_links');
    }
};
