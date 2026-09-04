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
 */
class ExcludeTemplatesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('is_template'), false);
    }
}
