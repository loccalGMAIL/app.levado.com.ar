<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El chequeo de duplicados del front (checkDuplicate) es solo advisory:
     * esta restricción garantiza en BD que no entren facturas duplicadas
     * (mismo tenant + proveedor + número de comprobante). Los NULL quedan
     * fuera del unique, así que las compras sin comprobante no se limitan.
     */
    public function up(): void
    {
        $duplicates = DB::table('purchases')
            ->select('tenant_id', 'supplier_id', 'invoice_number')
            ->whereNotNull('invoice_number')
            ->groupBy('tenant_id', 'supplier_id', 'invoice_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $detail = $duplicates
                ->map(fn ($d) => "tenant {$d->tenant_id} / proveedor {$d->supplier_id} / factura {$d->invoice_number}")
                ->implode('; ');

            throw new RuntimeException(
                "No se puede crear el índice único: hay compras duplicadas que resolver primero ({$detail})."
            );
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'supplier_id', 'invoice_number'],
                'purchases_tenant_supplier_invoice_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique('purchases_tenant_supplier_invoice_unique');
        });
    }
};
