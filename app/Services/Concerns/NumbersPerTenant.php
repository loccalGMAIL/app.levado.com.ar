<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un registro numerado por negocio (amasado #12, cierre #4): toma el
 * siguiente número (contando los borrados, para no reusarlo) y, si dos altas
 * simultáneas chocan contra el unique (tenant, number), reintenta.
 */
trait NumbersPerTenant
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass  modelo con SoftDeletes y columnas tenant_id/number
     * @param  array<string, mixed>  $attributes  incluye tenant_id
     * @return TModel
     */
    protected function createNumbered(string $modelClass, array $attributes): Model
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($modelClass, $attributes) {
                    $query = $modelClass::query();

                    if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                        $query->withoutGlobalScope(SoftDeletingScope::class);
                    }

                    $number = (int) $query->where('tenant_id', $attributes['tenant_id'])->max('number') + 1;

                    return $modelClass::query()->create($attributes + ['number' => $number]);
                });
            } catch (QueryException $exception) {
                if (++$attempts >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
