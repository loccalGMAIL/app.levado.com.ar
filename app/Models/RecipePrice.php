<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RecipePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipePrice extends Model
{
    /** @use HasFactory<RecipePriceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'price_list_id',
        'recipe_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
