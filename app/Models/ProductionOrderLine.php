<?php

namespace App\Models;

use App\Enums\LineMeasure;
use App\Enums\Unit;
use Database\Factories\ProductionOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de pedido: artículo + cantidad. Sin tenant_id propio — hereda por
 * el pedido, nunca se consulta suelta (ver ProductionOrderRequest).
 */
class ProductionOrderLine extends Model
{
    /** @use HasFactory<ProductionOrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'production_order_request_id',
        'product_id',
        'quantity',
        'unit',
        'measure',
        'position',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'measure' => 'unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => Unit::class,
            'measure' => LineMeasure::class,
        ];
    }

    /**
     * Cantidad como se pidió, para las vistas de sólo lectura y las planillas:
     * "30 u" o "1,5 lata (99 u)". Requiere `product.recipe` cargada cuando la
     * línea está en unidades de producción.
     */
    public function quantityLabel(): string
    {
        $quantity = (float) $this->quantity;
        $equivalent = $this->equivalentLabel();

        if ($equivalent === null) {
            return number_format($quantity, 2, ',', '.').' '.$this->unit->short();
        }

        return $this->trimmed($quantity).' '.$this->product->recipe->productionUnitLabel().' ('.$equivalent.')';
    }

    /**
     * Equivalente de una línea en unidades de producción, en unidades del
     * artículo y redondeado como se va a producir: "9 u", o "≈ 17 u" cuando
     * el redondeo cambió el valor (¼ de lata de 66 u son 16,5). Null si la
     * línea ya está en unidades, o no se puede convertir.
     */
    public function equivalentLabel(): ?string
    {
        if ($this->measure === LineMeasure::Unit || $this->product?->recipe === null) {
            return null;
        }

        $exact = $this->product->baseQuantityFor((float) $this->quantity, $this->measure);

        if ($exact === null) {
            return null;
        }

        $rounded = $this->product->roundForProduction($exact);

        return (abs($rounded - $exact) > 0.0001 ? '≈ ' : '').$this->trimmed($rounded).' '.$this->unit->short();
    }

    private function trimmed(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    /**
     * @return BelongsTo<ProductionOrderRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRequest::class, 'production_order_request_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
