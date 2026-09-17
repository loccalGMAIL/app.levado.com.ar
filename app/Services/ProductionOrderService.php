<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\DeliveryDestinationType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionOrderType;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
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
     * Crea una orden numerándola en la misma transacción (salvo que sea una
     * plantilla: is_template=true no consume número, no es una orden real
     * todavía). Único punto de creación — tanto el alta manual
     * (ProductionOrderController::store()) como ProductionOrderDuplicator
     * pasan por acá para que el número nunca se asigne en dos lugares.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createOrder(Tenant $tenant, array $attributes): ProductionOrder
    {
        if ($attributes['is_template'] ?? false) {
            return $tenant->productionOrders()->create($attributes);
        }

        return DB::transaction(function () use ($tenant, $attributes) {
            try {
                return $tenant->productionOrders()->create([...$attributes, 'number' => $this->reserveNumber($tenant)]);
            } catch (QueryException) {
                // Carrera rarísima entre dos altas concurrentes del mismo
                // negocio: el número reservado ya fue tomado por la otra.
                // Mismo patrón de reintento que StockService::lockedLevelRow().
                return $tenant->productionOrders()->create([...$attributes, 'number' => $this->reserveNumber($tenant)]);
            }
        });
    }

    /**
     * Toma el contador de órdenes del negocio con lock pesimista y lo
     * incrementa — mismo patrón que StockService::lockedLevelRow() (lock de
     * fila dentro de la transacción, con el unique de la base como red).
     */
    private function reserveNumber(Tenant $tenant): int
    {
        $row = DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();
        $number = (int) $row->next_production_order_number;

        DB::table('tenants')->where('id', $tenant->id)->update([
            'next_production_order_number' => $number + 1,
        ]);

        return $number;
    }

    /**
     * Agrega un pedido a la orden, numerándolo dentro de ella ("Pedido 1",
     * "Pedido 2"... vía la columna position, reusada como número). Lockea la
     * orden padre para que dos altas concurrentes de pedidos en la misma
     * orden no calculen el mismo MAX+1.
     *
     * withTemplates() es obligatorio: sin él, el global scope de
     * ExcludeTemplatesScope hace que el lock falle con 404 cuando se está
     * armando una plantilla (ProductionOrderDuplicator).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addRequest(ProductionOrder $order, array $attributes): ProductionOrderRequest
    {
        // tenant_id explícito: el auto-fill de BelongsToTenant depende de que
        // haya un Tenant bindeado en el container, que no está garantizado
        // fuera de un request HTTP (tests de servicio, artisan).
        $attributes = ['tenant_id' => $order->tenant_id, ...$attributes];

        return DB::transaction(function () use ($order, $attributes) {
            $locked = ProductionOrder::withTemplates()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $nextPosition = fn () => (int) $locked->productionOrderRequests()->max('position') + 1;

            try {
                return $locked->productionOrderRequests()->create([...$attributes, 'position' => $nextPosition()]);
            } catch (QueryException) {
                return $locked->productionOrderRequests()->create([...$attributes, 'position' => $nextPosition()]);
            }
        });
    }

    /**
     * Reemplaza el set completo de líneas de un pedido en una sola operación:
     * las que ya existen (traen `id`) se actualizan, las nuevas se crean y
     * las que la grilla ya no manda se borran. Único camino de escritura de
     * líneas — evita que store()/destroy() sueltos se desincronicen en el
     * chequeo de producible o en position/unit (como pasaba antes).
     *
     * Un `id` que no pertenece a este pedido (de otro pedido, o inventado) no
     * matchea contra $existing y se trata como alta — nunca "roba" una línea
     * ajena. La pertenencia de los product_id al gate producible ya la validó
     * SyncProductionOrderLinesRequest antes de llegar acá.
     *
     * @param  array<int, array{id?: int|null, product_id: int, quantity: float|string}>  $lines
     * @return int cantidad de líneas que quedaron en el pedido
     */
    public function syncLines(ProductionOrderRequest $request, array $lines): int
    {
        // producible(): defensa en profundidad, mismo gate que ya validó
        // SyncProductionOrderLinesRequest — si algo cambió entre la
        // validación y acá (carrera rarísima), un id que ya no es
        // producible simplemente no aparece y su línea no se guarda.
        $productIds = collect($lines)->pluck('product_id')->unique()->values();
        $products = Product::query()->producible()->whereIn('id', $productIds)->get()->keyBy('id');

        // Filtrado antes de numerar: una línea sin producto no ocupa un hueco
        // en position, y $i queda 0..N-1 sobre lo que realmente se guarda.
        $validLines = array_values(array_filter(
            $lines,
            fn (array $line) => $products->has($line['product_id']),
        ));

        return DB::transaction(function () use ($request, $validLines, $products) {
            /** @var EloquentCollection<int, ProductionOrderLine> $existing */
            $existing = $request->lines()->get()->keyBy('id');
            $kept = [];

            foreach ($validLines as $i => $line) {
                $product = $products->get($line['product_id']);
                $attributes = [
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit' => $product->unit->value,
                    'position' => $i + 1,
                ];

                $current = $existing->get($line['id'] ?? null);
                $saved = $current !== null
                    ? tap($current)->update($attributes)
                    : $request->lines()->create($attributes);

                $kept[] = $saved->id;
            }

            $request->lines()->whereNotIn('id', $kept ?: [0])->delete();

            return count($kept);
        });
    }

    /**
     * El pedido más reciente al mismo destino que $request, para precargar
     * su grilla ("Traer del pedido anterior"). Se excluye la ORDEN actual
     * entera, no sólo este pedido: dos pedidos al mismo destino en la misma
     * orden son un caso real, y traer el hermano de hoy confundiría. Las
     * plantillas quedan afuera solas — el whereHas aplica el global scope
     * ExcludeTemplatesScope de ProductionOrder (no agregar withTemplates()
     * acá "por las dudas": rompería justo esta exclusión). Los borradores sí
     * cuentan — se busca "lo que pedí la última vez", no sólo lo producido.
     */
    public function previousRequestFor(ProductionOrderRequest $request): ?ProductionOrderRequest
    {
        return ProductionOrderRequest::query()
            ->where('destination_type', $request->destination_type->value)
            ->where('destination_id', $request->destination_id)
            ->where('production_order_id', '!=', $request->production_order_id)
            ->whereHas('productionOrder', fn ($query) => $query->where('status', '!=', ProductionOrderStatus::Cancelled->value))
            ->with(['lines.product', 'productionOrder'])
            ->join('production_orders', 'production_orders.id', '=', 'production_order_requests.production_order_id')
            ->orderByDesc('production_orders.scheduled_for')
            ->orderByDesc('production_orders.id')
            ->select('production_order_requests.*')
            ->first();
    }

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
        return $this->previewFor($this->aggregate($order), $order->location);
    }

    /**
     * Igual que preview(), pero sobre pares artículo+cantidad en memoria, sin
     * que exista una orden persistida — lo usa la orden instantánea para
     * mostrar el consumo combinado antes de crear nada.
     *
     * @param  Collection<int, array{product: Product, quantity: float}>  $pairs
     * @return array{lines: array<int, array<string, mixed>>, material_cost: float, labor_cost: float, total_cost: float}
     */
    public function previewFor(Collection $pairs, Location $location): array
    {
        $consumptions = $pairs->map(
            fn (array $entry) => $this->productions->baseConsumption($entry['product'], $entry['quantity'])
        );

        $items = $this->mergeBase($consumptions->pluck('items'));
        $laborCost = $consumptions->sum('labor_cost');

        return $this->productions->summarize($items, $location, $laborCost);
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
     * Orden instantánea: un solo paso, un solo destino. Crea la orden (tipo
     * Instant), la confirma, arma su único pedido con las líneas dadas, y la
     * produce — todo en una transacción, sin que el usuario vea los pasos
     * intermedios. Reusa createOrder()/addRequest()/transitionTo()/produce()
     * tal cual, sin duplicar ninguna regla.
     *
     * El destino es informativo (arma la planilla): el stock entra igual al
     * obrador, nunca al destino elegido — ver decision-multi-sucursal.md.
     *
     * @param  Collection<int, array{product: Product, quantity: float}>  $items  ya agrupados por artículo (sumar cantidades repetidas es responsabilidad del caller)
     */
    public function produceInstant(
        Tenant $tenant,
        User $user,
        DeliveryDestinationType $destinationType,
        int $destinationId,
        Collection $items,
        ?string $notes = null,
    ): ProductionOrder {
        abort_if($items->isEmpty(), 422, 'La orden no tiene artículos para producir.');

        return DB::transaction(function () use ($tenant, $user, $destinationType, $destinationId, $items, $notes) {
            $order = $this->createOrder($tenant, [
                'location_id' => $tenant->defaultLocation()->id,
                'type' => ProductionOrderType::Instant->value,
                'scheduled_for' => now()->toDateString(),
                'status' => ProductionOrderStatus::Draft->value,
                'is_template' => false,
                'notes' => $notes,
                'user_id' => $user->id,
            ]);

            $request = $this->addRequest($order, [
                'destination_type' => $destinationType->value,
                'destination_id' => $destinationId,
            ]);

            foreach ($items as $entry) {
                $request->lines()->create([
                    'product_id' => $entry['product']->id,
                    'quantity' => $entry['quantity'],
                    'unit' => $entry['product']->unit->value,
                ]);
            }

            $this->transitionTo($order, ProductionOrderStatus::Confirmed, $user);
            $this->produce($order, $user);

            return $order->fresh();
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
