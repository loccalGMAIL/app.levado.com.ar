<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orden de los listados por ?sort= y ?dir=. Cada controller declara qué
 * columnas se pueden pedir (lista blanca: request('sort') nunca llega directo
 * al ORDER BY) y con qué se ordena cada una: una columna, una expresión, una
 * subconsulta (para ordenar por el nombre de una relación sin join) o un
 * closure cuando hace falta SQL a mano.
 *
 * Siempre suma un desempate: sin él, las filas con el mismo valor (mismo
 * estado, misma categoría) no tienen un orden fijo y al paginar se pueden
 * repetir o saltear registros entre páginas.
 *
 * Uso: ->tap(fn (Builder $q) => $this->sortListing($q, [...], fn (Builder $q) => $q->orderBy('name'), 'ingredients.id'))
 */
trait SortsListings
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string|Expression|Builder<covariant \Illuminate\Database\Eloquent\Model>|Closure(Builder<covariant \Illuminate\Database\Eloquent\Model>, 'asc'|'desc'): mixed>  $sortable  clave de la URL → con qué se ordena
     * @param  Closure(Builder<covariant \Illuminate\Database\Eloquent\Model>): mixed  $fallback  orden por defecto, cuando no se pidió una columna válida
     * @param  string  $tiebreaker  columna única (calificada si hay joins) que fija el orden entre empates
     * @param  string  $sortParam  nombre del parámetro de la URL; cambia cuando una misma pantalla tiene dos tablas
     */
    protected function sortListing(
        Builder $query,
        array $sortable,
        Closure $fallback,
        string $tiebreaker,
        string $sortParam = 'sort',
        string $dirParam = 'dir',
    ): void {
        $sort = request($sortParam);

        if (! is_string($sort) || ! array_key_exists($sort, $sortable)) {
            $fallback($query);
            $query->orderBy($tiebreaker);

            return;
        }

        $dir = request($dirParam) === 'desc' ? 'desc' : 'asc';
        $column = $sortable[$sort];

        if ($column instanceof Closure) {
            $column($query, $dir);
        } else {
            $query->orderBy($column, $dir);
        }

        $query->orderBy($tiebreaker, $dir);
    }
}
