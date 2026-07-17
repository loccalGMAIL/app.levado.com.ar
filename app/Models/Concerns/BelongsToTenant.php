<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aislamiento estructural entre tenants (defensa en profundidad).
 *
 * Cuando hay un tenant resuelto en el container (middleware SetTenantContext),
 * TODA query Eloquent sobre el modelo queda acotada a ese tenant y todo create
 * recibe tenant_id automáticamente si no lo trae. Un registro de otro tenant se
 * vuelve invisible: el route-model binding da 404 y un find() devuelve null,
 * aunque el controller olvide scopear a mano.
 *
 * Fuera del contexto web con tenant (backoffice /admin, comandos artisan,
 * tests sin request) no hay tenant bound y el scope no aplica: esos contextos
 * son cross-tenant por diseño y siguen scopeando explícitamente.
 *
 * Las policies y las reglas exists scopeadas por tenant se mantienen como
 * segunda capa — este trait es la red, no reemplaza el scoping explícito
 * (`$tenant->relación()`), que sigue siendo la convención al escribir queries.
 *
 * Quedan fuera a propósito: TenantUser (el middleware lo consulta ANTES de
 * resolver el tenant, y isSuperAdmin() debe ver la membresía en Levado HQ
 * durante la impersonación), TenantSetting (infra, siempre accedido vía la
 * relación del tenant) y AdminAuditLog (registro del backoffice, cross-tenant).
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->bound(Tenant::class)) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('tenant_id'),
                    app(Tenant::class)->id,
                );
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('tenant_id') === null && app()->bound(Tenant::class)) {
                $model->setAttribute('tenant_id', app(Tenant::class)->id);
            }
        });
    }
}
