<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\ProductionOrderStatus;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor de la orden de producción: agrega los pedidos (uno por destino) en
 * un solo consumo de insumos y una sola tanda de producciones, reusando el
 * motor de ProductionService por artículo — no duplica BOM ni ledger.
 */
class ProductionOrderService
{
    public function __construct(private ProductionService $productions) {}

    /**
     * Suma la cantidad pedida por artículo a través de todos los pedidos de
     * la orden. Es lo que se produce y lo que muestra el resumen agregado.
     *
     * @return Collection<int, array{product: Product, quantity: float}>
     */
    public function aggregate(ProductionOrder $order): Collection
    {
        // with('product.recipe'/'product.tenant'): dos líneas de la orden
        // pueden compartir artículo, así que product se hidrata como colección
        // de más de una fila — sin recipe/tenant precargados, leerlos más
        // abajo (guardProducible, defaultLocation()) sería un lazy load real
        // y preventLazyLoading explota (mismo cuidado que ProductionController::create).
        $lines = ProductionOrderLine::query()
            ->whereHas('request', fn ($query) => $query->where('production_order_id', $order->id))
            ->with(['product.recipe', 'product.tenant'])
            ->get();

        return $lines->groupBy('product_id')
            ->map(fn (Collection $group) => [
                'product' => $group->first()->product,
                'quantity' => (float) $group->sum('quantity'),
            ])
            ->values();
    }

    /**
     * Preview combinado: el consumo de insumos y la mano de obra de todos los
     * artículos de la orden, sumados por insumo, contra el stock del obrador.
     * Mismo array shape que ProductionService::preview() para reusar el markup.
     *
     * @return array{lines: array<int, array<string, mixed>>, material_cost: float, labor_cost: float, total_cost: float}
     */
    public function preview(ProductionOrder $order): array
    {
        $aggregated = $this->aggregate($order);

        $consumptions = $aggregated->map(
            fn (array $entry) => $this->productions->baseConsumption($entry['product'], $entry['quantity'])
        );

        $items = $this->mergeBase($consumptions->pluck('items'));
        $laborCost = $consumptions->sum('labor_cost');

        return $this->productions->summarize($items, $order->location, $laborCost);
    }

    /**
     * Fabrica todos los artículos agregados de la orden, en una sola
     * transacción: una Production por artículo (atada a la orden) y la orden
     * pasa a Done. Los artículos se recorren ordenados por id para que dos
     * órdenes concurrentes que compartan insumos tomen los locks de
     * stock_levels en el mismo orden entre sí (cada ProductionService::produce()
     * ya ordena sus propios movimientos — esto ordena entre llamadas).
     */
    public function produce(ProductionOrder $order, User $user): void
    {
        abort_unless(
            $order->status->canTransitionTo(ProductionOrderStatus::Done),
            422,
            'La orden no está en un estado que permita producir.',
        );

        $aggregated = $this->aggregate($order)->sortBy(fn (array $entry) => $entry['product']->id)->values();
        abort_if($aggregated->isEmpty(), 422, 'La orden no tiene líneas para producir.');

        DB::transaction(function () use ($order, $aggregated, $user) {
            foreach ($aggregated as $entry) {
                $production = $this->productions->produce($entry['product'], $entry['quantity'], null, $user);
                $production->update(['production_order_id' => $order->id]);
            }

            $this->applyTransition($order, ProductionOrderStatus::Done);
        });
    }

    /**
     * Anula la orden: revierte cada producción que generó (idempotente, vía
     * ProductionService::cancel()) y marca la orden como anulada. A
     * diferencia de transitionTo(), esto es lo único que puede llegar a
     * Cancelled — no pasa por canTransitionTo() porque también hay que poder
     * anular una orden ya Done (revertir lo producido).
     */
    public function cancel(ProductionOrder $order, User $user): void
    {
        if ($order->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($order, $user) {
            foreach ($order->productions as $production) {
                $this->productions->cancel($production, $user);
            }

            $this->applyTransition($order, ProductionOrderStatus::Cancelled);
        });
    }

    /**
     * Cambios de estado manuales (Draft<->Confirmed, Confirmed->InProduction).
     * Done y Cancelled tienen su propia puerta (produce()/cancel()) porque
     * mueven o revierten stock — pasar por acá los dejaría desincronizados.
     */
    public function transitionTo(ProductionOrder $order, ProductionOrderStatus $next, User $user): void
    {
        abort_if(
            in_array($next, [ProductionOrderStatus::Done, ProductionOrderStatus::Cancelled], true),
            422,
            'Para ese cambio de estado usá producir o anular.',
        );
        abort_unless(
            $order->status->canTransitionTo($next),
            422,
            "No se puede pasar de {$order->status->label()} a {$next->label()}.",
        );

        $this->applyTransition($order, $next);
    }

    private function applyTransition(ProductionOrder $order, ProductionOrderStatus $next): void
    {
        $timestamps = match ($next) {
            ProductionOrderStatus::Confirmed => ['confirmed_at' => now()],
            ProductionOrderStatus::Done => ['produced_at' => now()],
            ProductionOrderStatus::Cancelled => ['cancelled_at' => now()],
            default => [],
        };

        $order->update(array_merge(['status' => $next->value], $timestamps));
    }

    /**
     * Suma por insumo (type+id) el consumo base de varios artículos —misma
     * idea que la agregación interna de RecipeExploder, pero entre artículos
     * distintos en vez de entre líneas de una sola receta.
     *
     * @param  Collection<int, Collection<int, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>>  $collections
     * @return Collection<int, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>
     */
    private function mergeBase(Collection $collections): Collection
    {
        $accumulator = [];

        foreach ($collections as $base) {
            foreach ($base as $entry) {
                $key = $entry['type']->value.':'.$entry['item']->id;

                if (isset($accumulator[$key])) {
                    $accumulator[$key]['quantity'] += $entry['quantity'];
                } else {
                    $accumulator[$key] = $entry;
                }
            }
        }

        return collect(array_values($accumulator));
    }
}
