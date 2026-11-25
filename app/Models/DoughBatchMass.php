<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Masa de un amasado: lo planeado y lo que sobró (+) o faltó (−), en la
 * unidad de rinde de la sub-receta. Solo registro: no existe stock de masa.
 */
class DoughBatchMass extends Model
{
    protected $fillable = [
        'dough_batch_id',
        'recipe_id',
        'planned_quantity',
        'leftover_quantity',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'float',
            'leftover_quantity' => 'float',
        ];
    }

    /**
     * @return BelongsTo<DoughBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DoughBatch::class, 'dough_batch_id');
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
