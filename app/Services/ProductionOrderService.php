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
    public function __construct(
        private ProductionService $productions,
        private RecurringProductionRequestService $recurring,
    ) {}

    /**
     * Crea una orden numerándola en la misma transacción (salvo que sea una
     * plantilla: is_template=true no consume número, no es una orden real
     * todavía). Único punto de creación — el alta manual
     * (ProductionOrderController::store()), orderForDate() y produceInstant()
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
                return $tenant->productionOrders()->create([...$attributes, 'number' => $this->reserveOrderNumber($tenant->id)]);
            } catch (QueryException) {
                // Carrera rarísima entre dos altas concurrentes del mismo
                // negocio: el número reservado ya fue tomado por la otra.
                // Mismo patrón de reintento que StockService::lockedLevelRow().
                return $tenant->productionOrders()->create([...$attributes, 'number' => $this->reserveOrderNumber($tenant->id)]);
            }
        });
    }

    /**
     * Toma un contador del negocio con lock pesimista y lo incrementa —
     * mismo patrón que StockService::lockedLevelRow() (lock de fila dentro
     * de la transacción, con el unique de la base como red). Un solo método
     * para los dos contadores (orden y pedido) — el nombre de columna nunca
     * viene de input, lo fijan los wrappers de abajo.
     */
    private function reserveCounter(int $tenantId, string $column): int
    {
        $row = DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();
        $number = (int) $row->{$column};

        DB::table('tenants')->where('id', $tenantId)->update([
            $column => $number + 1,
        ]);

        return $number;
    }

    private function reserveOrderNumber(int $tenantId): int
    {
        return $this->reserveCounter($tenantId, 'next_production_order_number');
    }

    private function reserveRequestNumber(int $tenantId): int
    {
        return $this->reserveCounter($tenantId, 'next_production_order_request_number');
    }

    /**
     * Agrega un pedido a la orden. Numera dos cosas distintas: `position`
     * (local a la orden — "Pedido 1", "Pedido 2"... ya no se muestra pero
     * sigue protegiendo el orden estable y el MAX+1) y `number` (identidad
     * propia del pedido, correlativa por negocio — "Pedido #123", igual que
     * la orden tiene la suya). Lockea la orden padre para que dos altas
     * concurrentes de pedidos en la misma orden no calculen el mismo MAX+1.
     *
     * MAX(position) es withTrashed(): un pedido borrado (soft delete) sigue
     * ocupando su position, y sin esto el próximo alta chocaría contra el
     * unique (production_order_id, position) apenas alguien borre y agregue.
     *
     * Los pedidos de una orden plantilla no consumen number (igual que la
     * plantilla misma no consume number de orden) — is_template no cambia
     * después de creada la orden, así que leerlo de $order (sin recargar)
     * es seguro.
     *
     * withTemplates() es obligatorio: sin él, el global scope de
     * ExcludeTemplatesScope hace que el lock falle con 404 cuando la orden
     * bloqueada es una plantilla.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addRequest(ProductionOrder $order, array $attributes): ProductionOrderRequest
    {
        // tenant_id explícito: el auto-fill de BelongsToTenant depende de que
        // haya un Tenant bindeado en el container, que no está garantizado
        // fuera de un request HTTP (tests de servicio, artisan).
        $attributes = ['tenant_id' => $order->tenant_id, ...$attributes];
        $isTemplate = $order->is_template;

        return DB::transaction(function () use ($order, $attributes, $isTemplate) {
            $locked = ProductionOrder::withTemplates()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $nextPosition = fn () => (int) $locked->productionOrderRequests()->withTrashed()->max('position') + 1;
            $number = fn () => $isTemplate ? null : $this->reserveRequestNumber($locked->tenant_id);

            try {
                return $locked->productionOrderRequests()->create([...$attributes, 'position' => $nextPosition(), 'number' => $number()]);
            } catch (QueryException) {
                return $locked->productionOrderRequests()->create([...$attributes, 'position' => $nextPosition(), 'number' => $number()]);
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
     * su grilla ("Traer del pedido anterior"). Wrapper de
     * previousRequestForDestination() que arma los argumentos desde un
     * pedido existente.
     */
    public function previousRequestFor(ProductionOrderRequest $request): ?ProductionOrderRequest
    {
        // loadMissing: el caller típico (route model binding) no trae la
        // relación tenant precargada, y preventLazyLoading la frenaría.
        return $this->previousRequestForDestination(
            $request->loadMissing('tenant')->tenant,
            $request->destination_type,
            $request->destination_id,
            excludeOrderId: $request->production_order_id,
        );
    }

    /**
     * El pedido más reciente a ese destino — separado de previousRequestFor()
     * para poder ofrecer "traer los artículos del último pedido" *antes* de
     * que el pedido nuevo exista (placeRequest(), el modal de alta). Se
     * excluye la ORDEN entera de $excludeOrderId, no sólo un pedido: dos
     * pedidos al mismo destino en la misma orden son un caso real, y traer
     * el hermano de hoy confundiría. Las plantillas quedan afuera solas — el
     * whereHas aplica el global scope ExcludeTemplatesScope de
     * ProductionOrder (no agregar withTemplates() acá "por las dudas":
     * rompería justo esta exclusión). Los borradores sí cuentan — se busca
     * "lo que pedí la última vez", no sólo lo producido.
     *
     * $tenant explícito (no confiar en el scope de BelongsToTenant): se
     * llama también desde contextos sin tenant bindeado en el container
     * (el materializador, artisan).
     */
    public function previousRequestForDestination(
        Tenant $tenant,
        DeliveryDestinationType $type,
        int $destinationId,
        ?int $excludeOrderId = null,
    ): ?ProductionOrderRequest {
        return ProductionOrderRequest::query()
            ->where('production_order_requests.tenant_id', $tenant->id)
            ->where('destination_type', $type->value)
            ->where('destination_id', $destinationId)
            ->when($excludeOrderId !== null, fn ($query) => $query->where('production_order_id', '!=', $excludeOrderId))
            ->whereHas('productionOrder', fn ($query) => $query->where('status', '!=', ProductionOrderStatus::Cancelled->value))
            ->with(['lines.product', 'productionOrder'])
            ->join('production_orders', 'production_orders.id', '=', 'production_order_requests.production_order_id')
            ->orderByDesc('production_orders.scheduled_for')
            ->orderByDesc('production_orders.id')
            ->select('production_order_requests.*')
            ->first();
    }

    /**
     * Da forma al JSON de "traer del pedido anterior" — un solo lugar para
     * las dos superficies que lo piden: dentro de un pedido existente
     * (ProductionOrderRequestController::previousLines()) y en el alta
     * suelta, antes de que el pedido nuevo exista
     * (ProductionRequestController::previousLines()). Filtra server-side los
     * artículos que dejaron de ser producibles — si se trajeran igual, el
     * guardado posterior fallaría con un 422 por renglón sin que el usuario
     * entienda por qué.
     *
     * @return array{found: bool, source?: array{label: string, scheduled_for: ?string}, lines?: array<int, array<string, mixed>>, skipped?: array<int, string>}
     */
    public function previousLinesPayload(Tenant $tenant, ?ProductionOrderRequest $previous): array
    {
        if ($previous === null) {
            return ['found' => false];
        }

        $producibleIds = $tenant->products()->producible()
            ->whereIn('id', $previous->lines->pluck('product_id'))
            ->pluck('id');

        [$lines, $skipped] = $previous->lines->partition(fn ($line) => $producibleIds->contains($line->product_id));

        return [
            'found' => true,
            'source' => [
                'label' => $previous->productionOrder->numberLabel().' · '.$previous->numberLabel(),
                'scheduled_for' => $previous->productionOrder->scheduled_for?->format('d/m/Y'),
            ],
            'lines' => $lines->values()->map(fn (ProductionOrderLine $line) => [
                'product_id' => $line->product_id,
                'name' => $line->product->name,
                'unit' => $line->unit->short(),
                'quantity' => (float) $line->quantity,
            ])->all(),
            'skipped' => $skipped->map(fn (ProductionOrderLine $line) => $line->product?->name ?? '—')->values()->all(),
        ];
    }

    /**
     * Carga un pedido sin que exista todavía la orden del día: encuentra-o-
     * crea la orden diaria de esa fecha (orderForDate()) y cuelga el pedido
     * de ella. Es el alta que usa el panadero — addRequest() sigue siendo el
     * alta de bajo nivel ("agregar a ESTA orden"); acá la diferencia es que
     * la orden la busca sola. Lo mismo usa el materializador de recurrencia
     * para generar cada instancia, así que las garantías (numeración,
     * validación de líneas) son las mismas para un pedido suelto y uno
     * generado.
     *
     * Si viene `recurrence`, el molde se crea ANTES del pedido, en la misma
     * transacción, y su id viaja a addRequest() — así la primera instancia
     * ya queda vinculada y el materializador no la duplica (su fecha ya
     * está en el set de existencia la primera vez que corra).
     *
     * `recurring_production_request_id` (distinto de `recurrence`) es para
     * el materializador: vincula la instancia a un molde que YA EXISTE, sin
     * crear uno nuevo — `recurrence` es para el alta de un panadero, que
     * recién ahí nace el molde.
     *
     * @param  array{
     *     destination_type: string, destination_id: int, scheduled_for: string,
     *     notes?: ?string,
     *     lines?: array<int, array{id?: int|null, product_id: int, quantity: float|string}>,
     *     copy_previous?: bool,
     *     recurrence?: array{weekdays: array<int, int>, ends_on?: ?string},
     *     recurring_production_request_id?: ?int,
     * }  $attributes
     */
    public function placeRequest(Tenant $tenant, array $attributes, ?User $user = null): ProductionOrderRequest
    {
        return DB::transaction(function () use ($tenant, $attributes, $user) {
            // Lock de la fila del negocio antes del find-or-create: dos altas
            // simultáneas para la misma fecha no deben crear dos órdenes del
            // día. Reentrante con el lock que reserveOrderNumber()/
            // reserveRequestNumber() vuelven a tomar más abajo — misma
            // transacción y conexión, no deadlockea.
            DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();

            $order = $this->orderForDate($tenant, $attributes['scheduled_for'], $user);
            abort_unless($order->isEditable(), 422, 'La orden de ese día ya no se puede editar.');

            $lines = $attributes['lines'] ?? [];

            $recurringId = $attributes['recurring_production_request_id'] ?? null;
            if (isset($attributes['recurrence'])) {
                $recurringId = $this->recurring->create($tenant, [
                    'destination_type' => $attributes['destination_type'],
                    'destination_id' => $attributes['destination_id'],
                    'weekdays' => $attributes['recurrence']['weekdays'],
                    'starts_on' => $attributes['scheduled_for'],
                    'ends_on' => $attributes['recurrence']['ends_on'] ?? null,
                    'lines' => $lines,
                ], $user)->id;
            }

            $request = $this->addRequest($order, [
                'destination_type' => $attributes['destination_type'],
                'destination_id' => $attributes['destination_id'],
                'notes' => $attributes['notes'] ?? null,
                'recurring_production_request_id' => $recurringId,
            ]);

            if (($attributes['copy_previous'] ?? false) && $lines === []) {
                $previous = $this->previousRequestForDestination(
                    $tenant,
                    DeliveryDestinationType::from($attributes['destination_type']),
                    (int) $attributes['destination_id'],
                    excludeOrderId: $order->id,
                );

                $lines = $previous?->lines->map(fn (ProductionOrderLine $line) => [
                    'product_id' => $line->product_id,
                    'quantity' => (float) $line->quantity,
                ])->all() ?? [];
            }

            if ($lines !== []) {
                $this->syncLines($request, $lines);
            }

            // setRelation en vez de dejar que $request->productionOrder
            // lazy-loadee: $order ya está en memoria, así que es la misma
            // fila — evita una consulta de más y el corte de preventLazyLoading.
            return $request->setRelation('productionOrder', $order);
        });
    }

    /**
     * La orden diaria de esa fecha, o una nueva en Draft. Sólo considera
     * órdenes tipo Daily y editables (Draft/Confirmed/InProduction — es
     * literalmente isEditable(), no se endurece la regla). Prioriza Draft si
     * hay más de una candidata. Una orden Done o Cancelled NO se reusa: un
     * pedido tardío (llegó después de que la tanda de la mañana ya se
     * produjo) abre una orden nueva para la misma fecha — es un caso real,
     * no un edge case. Espontánea e Instantánea nunca se reusan (quedan
     * afuera del filtro de tipo).
     */
    private function orderForDate(Tenant $tenant, string $date, ?User $user): ProductionOrder
    {
        // A lo sumo 3 candidatas por fecha (una por estado editable) — se
        // ordena en PHP, no con field() de MySQL, para que el mismo código
        // corra igual contra SQLite (tests) y MySQL (todo lo demás).
        $candidates = $tenant->productionOrders()
            ->whereDate('scheduled_for', $date)
            ->where('type', ProductionOrderType::Daily->value)
            ->whereIn('status', [
                ProductionOrderStatus::Draft->value,
                ProductionOrderStatus::Confirmed->value,
                ProductionOrderStatus::InProduction->value,
            ])
            ->orderBy('id')
            ->get();

        // Prioriza Draft sobre Confirmed/InProduction si hay más de una
        // candidata: colgarse de un borrador molesta menos que tocar una
        // orden ya confirmada.
        $priority = [ProductionOrderStatus::Draft, ProductionOrderStatus::Confirmed, ProductionOrderStatus::InProduction];
        $existing = $candidates->sortBy(fn (ProductionOrder $order) => array_search($order->status, $priority, true))->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createOrder($tenant, [
            'location_id' => $tenant->defaultLocation()->id,
            'type' => ProductionOrderType::Daily->value,
            'scheduled_for' => $date,
            'status' => ProductionOrderStatus::Draft->value,
            'is_template' => false,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Sucursales/repartidores activos + el catálogo liviano de artículos
     * producibles (id/nombre/unidad, no el modelo completo) — lo que
     * necesitan los modales "+ Nuevo pedido" / "Orden instantánea", se abran
     * desde Órdenes de producción o desde el dashboard. Con el gate
     * producible invertido son ~195 artículos; viaja una sola vez por
     * página, compartido por referencia entre las grillas.
     *
     * @return array{0: Collection, 1: Collection, 2: Collection}
     */
    public function destinationAndCatalogData(Tenant $tenant): array
    {
        $locations = $tenant->locations()->active()->orderBy('name')->get();
        $deliveryPeople = $tenant->deliveryPeople()->active()->orderBy('name')->get();
        $products = $tenant->products()->producible()->orderBy('name')->get(['id', 'name', 'unit'])
            ->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit->short()]);

        return [$locations, $deliveryPeople, $products];
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
