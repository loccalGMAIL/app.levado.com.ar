<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Excluye plantillas (is_template = true) de cualquier consulta por defecto,
 * ergonomía tipo SoftDeletes. Sin este scope, cualquier listado que olvide un
 * where suelto muestra plantillas entre las órdenes del día — la misma
 * trampa que is_semi_elaborate paga hoy sin scope. Ver ProductionOrder::
 * withTemplates()/onlyTemplates() para optar explícitamente.
 *
 * La UI de plantillas (guardar una orden como plantilla, usarla) se retiró:
 * la recurrencia por pedido la reemplaza — ver RecurringProductionRequest.
 * is_template y este scope quedan dormidos a propósito, no se borran todavía:
 * ninguna pantalla crea plantillas nuevas, pero sacar la columna toca
 * createOrder()/addRequest() (que siguen leyendo is_template para no numerar
 * de más) y varios tests, para un beneficio invisible. Candidato a una
 * migración drop_is_template_from_production_orders más adelante.
 */
class ExcludeTemplatesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('is_template'), false);
    }
}
