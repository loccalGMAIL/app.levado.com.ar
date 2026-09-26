<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DeliveryPerson deja de ser un tipo de destino válido (ver
 * App\Enums\DeliveryDestinationType) — el repartidor lleva la mercadería,
 * no la recibe. Repunta los pedidos existentes con destination_type=
 * 'delivery_person' a la sucursal principal del negocio (datos de prueba,
 * confirmado con el usuario: no hay forma de saber qué cliente real
 * representaba cada repartidor-destino viejo).
 *
 * UPDATE crudo, no Eloquent: los global scopes (BelongsToTenant,
 * ExcludeTemplatesScope) no aplican en migraciones y el tenant no está
 * bindeado en el container fuera de un request HTTP.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['production_order_requests', 'recurring_production_requests'] as $table) {
            $tenantIds = DB::table($table)
                ->where('destination_type', 'delivery_person')
                ->distinct()
                ->pluck('tenant_id');

            foreach ($tenantIds as $tenantId) {
                $locationId = $this->defaultLocationId((int) $tenantId);

                DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('destination_type', 'delivery_person')
                    ->update(['destination_type' => 'location', 'destination_id' => $locationId]);
            }
        }
    }

    /** Espejo en SQL de Tenant::defaultLocation() — sin crear un Eloquent Tenant. */
    private function defaultLocationId(int $tenantId): int
    {
        $locationId = DB::table('locations')->where('tenant_id', $tenantId)->where('is_default', true)->value('id')
            ?? DB::table('locations')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        return $locationId ?? DB::table('locations')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Casa Central',
            'is_default' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * No reversible: no hay forma de reconstruir qué repartidor era cada
     * destino repuntado a sucursal.
     */
    public function down(): void {}
};
