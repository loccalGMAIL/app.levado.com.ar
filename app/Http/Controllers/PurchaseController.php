<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Enums\Unit;
use App\Http\Requests\StorePurchaseLineRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseLineRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\InvoiceImagePreparer;
use App\Services\ProductLinkMemory;
use App\Services\PurchaseLineRecorder;
use App\Services\RecipeCostPropagator;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseController extends Controller
{
    /**
     * Valor centinela del select de match: marca el renglón como consumo personal
     * en vez de asociarlo al catálogo. Ver matchLine().
     */
    public const EXCLUDED_MATCH = 'excluded';

    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly PurchaseLineRecorder $lineRecorder,
        private readonly StockService $stock,
        private readonly InvoiceImagePreparer $imagePreparer,
        private readonly ProductLinkMemory $linkMemory,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortCol = request('sort', 'date');
        $sortDir = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $tenant->purchases()
            ->with('supplier')
            ->withCount('lines')
            // Resueltos = imputados + consumo personal. El indicador ámbar/verde mide
            // "no queda nada por decidir", no "todo tiene costo aplicado".
            ->withCount(['lines as resolved_count' => fn ($q) => $q->where(
                fn ($q2) => $q2->whereNotNull('cost_applied_at')->orWhereNotNull('excluded_at')
            )])
            ->withSum('lines as net_total', 'subtotal')
            ->when(request('search'), function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('invoice_number', 'like', "%{$s}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$s}%"));
                });
            })
            ->when(request('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(request('from'), fn ($q, $date) => $q->where('invoice_date', '>=', $date))
            ->when(request('to'), fn ($q, $date) => $q->where('invoice_date', '<=', $date));

        match ($sortCol) {
            'invoice_number' => $query->orderBy('invoice_number', $sortDir)->orderByDesc('purchases.id'),
            'supplier' => $query->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
                ->select('purchases.*')
                ->orderBy('suppliers.name', $sortDir)
                ->orderByDesc('purchases.id'),
            'items' => $query->orderBy('lines_count', $sortDir)->orderByDesc('id'),
            'total' => $query->orderBy('invoice_total', $sortDir)->orderByDesc('id'),
            default => $query->orderBy('invoice_date', $sortDir)->orderByDesc('id'),
        };

        $purchases = $query->paginate(20)->withQueryString();

        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();
        $includeIva = filter_var($tenant->getSetting('purchase_price_includes_iva', '1'), FILTER_VALIDATE_BOOLEAN);

        return view('purchases.index', compact('purchases', 'suppliers', 'includeIva'));
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        abort_unless(
            $tenant->suppliers()->where('id', $request->validated('supplier_id'))->exists(),
            403,
        );

        $data = $request->safe()->except('invoice');
        if ($request->hasFile('invoice')) {
            $data['invoice_image_path'] = $this->storeInvoiceImage($request->file('invoice'), $tenant);
        }

        $purchase = $tenant->purchases()->create($data);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase',
            targetId: $purchase->id,
            action: 'purchase.created',
            payload: ['invoice_number' => $purchase->invoice_number, 'invoice_date' => $purchase->invoice_date->toDateString()],
            tenantId: $tenant->id,
        );

        return redirect()->route('purchases.show', $purchase)->with('status', 'Compra registrada.');
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);
        $tenant = app(Tenant::class);

        abort_unless(
            $tenant->suppliers()->where('id', $request->validated('supplier_id'))->exists(),
            403,
        );

        $data = $request->safe()->except('invoice');
        if ($request->hasFile('invoice')) {
            $previousImagePath = $purchase->invoice_image_path;
            $data['invoice_image_path'] = $this->storeInvoiceImage($request->file('invoice'), $tenant);
            if ($previousImagePath) {
                $this->deleteInvoiceImage($previousImagePath);
            }
        }

        $purchase->update($data);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase',
            targetId: $purchase->id,
            action: 'purchase.updated',
            payload: ['invoice_number' => $purchase->invoice_number, 'invoice_date' => $purchase->invoice_date->toDateString()],
            tenantId: $tenant->id,
        );

        return redirect()->route('purchases.show', $purchase)->with('status', 'Compra actualizada.');
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $invoiceNumber = $request->string('invoice_number')->trim()->value();
        $supplierId = $request->integer('supplier_id');
        $excludeId = $request->integer('exclude_id');

        if (blank($invoiceNumber) || $supplierId === 0) {
            return response()->json(['duplicate' => false]);
        }

        $tenant = app(Tenant::class);

        $existing = $tenant->purchases()
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', $invoiceNumber)
            ->when($excludeId > 0, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();

        if ($existing === null) {
            return response()->json(['duplicate' => false]);
        }

        return response()->json([
            'duplicate' => true,
            'purchase_id' => $existing->id,
            'date' => $existing->invoice_date->format('d/m/Y'),
        ]);
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);
        $tenant = app(Tenant::class);

        $purchase->load(['supplier', 'lines']);

        // Todos, no sólo los activos: el select de edición es `required`, así que si el
        // proveedor de la compra fue dado de baja su opción no existiría, el select caería
        // en la opción vacía y el navegador bloquearía el guardado de cualquier otro campo.
        $suppliers = $tenant->suppliers()->orderBy('name')->get();
        $units = Unit::cases();

        return view('purchases.show', compact('purchase', 'suppliers', 'units'));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);

        $invoiceNumber = $purchase->invoice_number;
        $tenantId = $purchase->tenant_id;
        $purchaseId = $purchase->id;
        $imagePath = $purchase->invoice_image_path;

        // Revertir el stock de las líneas aplicadas ANTES del delete: el cascade
        // de la FK borra las líneas y perderíamos la referencia para el contramovimiento.
        DB::transaction(function () use ($purchase) {
            $purchase->lines()
                ->whereNotNull('cost_applied_at')
                ->get()
                ->each(fn (PurchaseLine $line) => $this->stock->reversePurchaseLineEntry($line, request()->user()));

            $purchase->delete(); // lines cascade via FK
        });

        if ($imagePath) {
            $this->deleteInvoiceImage($imagePath);
        }

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'purchase',
            targetId: $purchaseId,
            action: 'purchase.deleted',
            payload: ['invoice_number' => $invoiceNumber],
            tenantId: $tenantId,
        );

        return redirect()->route('purchases.index')->with('status', 'Compra eliminada.');
    }

    public function updateLinePrice(Request $request, Purchase $purchase, PurchaseLine $line): JsonResponse
    {
        $this->authorize('update', $purchase);
        // Evita que apply()/recompute() lazy-loadeen línea→compra→tenant.
        $line->setRelation('purchase', $purchase->loadMissing('tenant'));

        $validated = $request->validate([
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $this->lineRecorder->recompute($line, [
            'raw_name' => $line->raw_name,
            'quantity_purchased' => (float) $line->quantity_purchased,
            'purchase_unit' => $line->purchase_unit->value,
            'unit_price' => (float) $validated['unit_price'],
            'iva_rate' => (float) $line->iva_rate,
            'percepcion_rate' => $line->percepcion_rate !== null ? (float) $line->percepcion_rate : null,
        ]);

        $line->refresh();

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.updated',
            payload: ['unit_price' => (float) $line->unit_price],
            tenantId: $purchase->tenant_id,
        );

        $totals = $purchase->lines()
            ->selectRaw('coalesce(sum(subtotal), 0) as total_subtotal')
            ->selectRaw('coalesce(sum(subtotal * iva_rate), 0) as total_iva')
            ->selectRaw('coalesce(sum(subtotal * coalesce(percepcion_rate, 0) / 100), 0) as total_percepcion')
            ->first();
        $totalSubtotal = (float) $totals->total_subtotal;
        $totalIva = (float) $totals->total_iva;
        $totalPercepcion = (float) $totals->total_percepcion;

        return response()->json([
            'unit_price' => (float) $line->unit_price,
            'total_subtotal' => $totalSubtotal,
            'total_iva' => $totalIva,
            'total_percepcion' => $totalPercepcion,
            'grand_total' => $totalSubtotal + $totalIva + $totalPercepcion,
        ]);
    }

    public function storeLine(StorePurchaseLineRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        // Detail = faithful digitised invoice. Adding a line just records what's
        // on the invoice; the cost is imputed later in the match step.
        $line = $this->lineRecorder->storePending($purchase, $request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.created',
            payload: ['raw_name' => $line->raw_name],
            tenantId: $purchase->tenant_id,
        );

        return back()->with('status', 'Renglón agregado.');
    }

    private function storeInvoiceImage(UploadedFile $file, Tenant $tenant): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        [$contents, $mime] = $this->imagePreparer->prepare($contents, $file->getMimeType() ?? 'image/jpeg');

        $extension = $mime === 'application/pdf' ? 'pdf' : 'jpg';
        $path = "purchases/{$tenant->id}/".Str::uuid()->toString().'.'.$extension;
        // Disco privado: los comprobantes son datos fiscales y no deben quedar
        // accesibles sin autenticación vía el symlink /storage.
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    /**
     * Disco donde vive el comprobante: 'local' (privado) para los nuevos,
     * con fallback a 'public' para archivos anteriores a la migración
     * (relocalizables con `php artisan invoices:relocate`).
     */
    private function invoiceDiskFor(string $path): ?string
    {
        return match (true) {
            Storage::disk('local')->exists($path) => 'local',
            Storage::disk('public')->exists($path) => 'public',
            default => null,
        };
    }

    private function deleteInvoiceImage(string $path): void
    {
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }

    public function invoiceImage(Purchase $purchase): StreamedResponse
    {
        $this->authorize('update', $purchase);

        $disk = blank($purchase->invoice_image_path) ? null : $this->invoiceDiskFor($purchase->invoice_image_path);

        abort_if($disk === null, 404);

        // Served through the app (not the /storage symlink) so it works on any host.
        return Storage::disk($disk)->response($purchase->invoice_image_path);
    }

    public function updateLine(UpdatePurchaseLineRequest $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorize('update', $purchase);
        // Evita que apply()/recompute() lazy-loadeen línea→compra→tenant.
        $line->setRelation('purchase', $purchase->loadMissing('tenant'));

        $this->lineRecorder->recompute($line, $request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.updated',
            payload: ['type' => $line->purchaseable_type, 'id' => $line->purchaseable_id],
            tenantId: $purchase->tenant_id,
        );

        return back()->with('status', 'Ítem actualizado y costo recalculado.');
    }

    public function destroyLine(Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorize('update', $purchase);

        DB::transaction(function () use ($line) {
            if ($line->isApplied()) {
                $this->stock->reversePurchaseLineEntry($line, request()->user());
            }

            $line->delete();
        });

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.deleted',
            payload: ['type' => $line->purchaseable_type, 'id' => $line->purchaseable_id],
            tenantId: $purchase->tenant_id,
        );

        return back()->with('status', 'Ítem eliminado.');
    }

    public function match(Purchase $purchase): View
    {
        $this->authorize('update', $purchase);
        $tenant = app(Tenant::class);

        $purchase->load(['supplier', 'lines']);

        // Todos, no sólo los activos (regla del proveedor inactivo, v0.10.0): un
        // renglón ya asociado a un ítem dado de baja debe seguir mostrando su
        // opción — si el select solo listara activos, caería en "— sin asociar —"
        // y guardar ese renglón revertiría stock y costo en silencio.
        $ingredients = $tenant->ingredients()->orderBy('name')->get();
        $packagings = $tenant->packagings()->orderBy('name')->get();

        // Catálogo para el lookup de Alpine, con la misma clave "tipo:id" que manda
        // el select: los ids de ingredients y packagings colisionan entre sí.
        $matchCatalog = $ingredients->mapWithKeys(fn ($i) => [
            "ingredient:{$i->id}" => [
                'unit' => $i->unit->value,
                'name' => $i->name,
                'subdivisions' => $i->subdivisions,
                'subdivisionLabel' => $i->subdivision_label,
            ],
        ])->merge($packagings->mapWithKeys(fn ($p) => [
            // Los descartables no tienen columna unit: siempre se compran por unidad.
            // El 'u' sintético los hace caer en el mismo camino que los insumos.
            "packaging:{$p->id}" => [
                'unit' => Unit::Unidad->value,
                'name' => $p->name,
                'subdivisions' => $p->subdivisions,
                'subdivisionLabel' => $p->subdivision_label,
            ],
        ]))->toArray();

        // Divisores recordados de facturas anteriores de este proveedor, indexados
        // por "tipo:id" para que el componente Alpine sólo los use si el renglón
        // sigue apuntando al mismo ítem que se recordó.
        $recalled = $this->linkMemory->recallMany(
            $tenant,
            $purchase->supplier_id,
            $purchase->lines->pluck('raw_name')->all(),
        );

        $rememberedPkgQty = $purchase->lines
            ->mapWithKeys(function (PurchaseLine $line) use ($recalled) {
                $hit = $recalled[$this->linkMemory->fold((string) $line->raw_name)] ?? null;

                return [$line->id => $hit === null || $hit['pkg_qty'] === null ? null : [
                    'selection' => "{$hit['purchaseable_type']}:{$hit['purchaseable_id']}",
                    'pkgQty' => $hit['pkg_qty'],
                ]];
            })
            ->filter()
            ->toArray();

        return view('purchases.match', compact(
            'purchase', 'ingredients', 'packagings', 'matchCatalog', 'rememberedPkgQty',
        ));
    }

    /**
     * Phase 2: associate a captured line with a catalog item and impute its cost.
     * Accepts an optional unit_cost override (used when units are incompatible and
     * the user provides the cost-per-catalog-unit directly from the match view).
     */
    public function matchLine(Request $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorize('update', $purchase);
        // Evita que apply()/recompute() lazy-loadeen línea→compra→tenant.
        $line->setRelation('purchase', $purchase->loadMissing('tenant'));

        $validated = $request->validate([
            'match' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'pkg_qty' => ['nullable', 'numeric', 'min:0.001'],
            'exclusion_note' => ['nullable', 'string', 'max:255'],
        ]);

        $match = $validated['match'] ?? null;
        $unitCost = isset($validated['unit_cost']) && $validated['unit_cost'] !== ''
            ? (float) $validated['unit_cost']
            : null;
        $pkgQty = isset($validated['pkg_qty']) && $validated['pkg_qty'] !== ''
            ? (float) $validated['pkg_qty']
            : null;

        $tenant = app(Tenant::class);

        // "— sin asociar —": mark the line as pending again (does not revert costs,
        // but does revert its stock entry so the ledger stays consistent).
        if (blank($match)) {
            DB::transaction(function () use ($line, $tenant, $purchase) {
                if ($line->isApplied()) {
                    $this->stock->reversePurchaseLineEntry($line, request()->user());
                }

                $line->update([
                    'purchaseable_type' => null,
                    'purchaseable_id' => null,
                    'cost_applied_at' => null,
                    'excluded_at' => null,
                    'exclusion_note' => null,
                ]);

                // Sin esto la próxima factura volvería a sugerir justo lo que
                // el usuario acaba de descartar.
                $this->linkMemory->forget($tenant, $purchase->supplier_id, $line->raw_name);
            });

            return back()->with('status', 'Renglón marcado como pendiente.');
        }

        // Centinela del select: el renglón no es del negocio (consumo personal del
        // titular en la factura del proveedor). No colisiona con un match real, que
        // siempre viaja como "tipo:id".
        if ($match === self::EXCLUDED_MATCH) {
            DB::transaction(function () use ($line, $request, $validated, $tenant, $purchase) {
                if ($line->isApplied()) {
                    $this->stock->reversePurchaseLineEntry($line, $request->user());
                }

                $line->update([
                    'purchaseable_type' => null,
                    'purchaseable_id' => null,
                    'cost_applied_at' => null,
                    'excluded_at' => now(),
                    'exclusion_note' => $validated['exclusion_note'] ?? null,
                ]);

                // Se olvida el vínculo, pero NO se recuerda la exclusión: la tabla
                // de alias para consumo personal recurrente está fuera de alcance
                // (ver feature-compras.md), y mezclarla acá rompería la invariante
                // de los tres estados del renglón.
                $this->linkMemory->forget($tenant, $purchase->supplier_id, $line->raw_name);
            });

            $this->recorder->record(
                actor: $request->user(),
                targetType: 'purchase_line',
                targetId: $line->id,
                action: 'purchase_line.excluded',
                payload: ['note' => $line->exclusion_note],
                tenantId: $purchase->tenant_id,
            );

            return back()->with('status', 'Renglón marcado como consumo personal.');
        }

        [$rawType, $id] = array_pad(explode(':', $match, 2), 2, null);
        $itemType = CatalogItemType::tryFrom((string) $rawType);
        abort_unless($itemType !== null && is_numeric($id), 422);
        $type = $itemType->value;

        $belongs = $itemType === CatalogItemType::Ingredient
            ? $tenant->ingredients()->whereKey($id)->exists()
            : $tenant->packagings()->whereKey($id)->exists();
        abort_unless($belongs, 422);

        try {
            DB::transaction(function () use ($line, $type, $id, $unitCost, $pkgQty) {
                // Asociar saca al renglón del estado "consumo personal", si venía de ahí.
                $line->update([
                    'purchaseable_type' => $type,
                    'purchaseable_id' => (int) $id,
                    'excluded_at' => null,
                    'exclusion_note' => null,
                ]);
                if ($unitCost !== null) {
                    $this->lineRecorder->applyWithCost($line, $unitCost);
                } else {
                    $this->lineRecorder->apply($line);
                }

                // La corrección a mano es la señal de máxima calidad: es lo que
                // hace que la próxima factura de este proveedor llegue vinculada.
                $this->linkMemory->remember($line, $pkgQty);
            });
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.matched',
            payload: ['type' => $type, 'id' => (int) $id],
            tenantId: $purchase->tenant_id,
        );

        return back()->with('status', 'Renglón asociado y costo actualizado.');
    }

    /**
     * Phase 2: apply every still-pending line that already has a suggested match.
     */
    public function applyLineSuggestions(Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $applied = 0;
        $skipped = 0;
        $failed = 0;
        $touchedIngredientIds = [];
        $touchedPackagingIds = [];

        $pending = $purchase->lines()
            ->whereNotNull('purchaseable_id')
            ->whereNull('cost_applied_at')
            ->whereNull('excluded_at')
            ->get();

        foreach ($pending as $line) {
            // Evita que apply()/recompute() lazy-loadeen línea→compra→tenant.
            $line->setRelation('purchase', $purchase->loadMissing('tenant'));
            try {
                DB::transaction(function () use ($line, &$touchedIngredientIds, &$touchedPackagingIds) {
                    $item = $this->lineRecorder->apply($line, propagate: false);

                    if ($item instanceof Ingredient) {
                        $touchedIngredientIds[] = $item->id;
                    } else {
                        $touchedPackagingIds[] = $item->id;
                    }

                    // Aceptar en masa también es una decisión humana. Sin esto la
                    // memoria sólo aprendería de las correcciones una por una, y
                    // el camino más usado no enseñaría nada.
                    $this->linkMemory->remember($line);
                });
                $applied++;
            } catch (HttpException $e) {
                if (str_contains($e->getMessage(), 'unidades no son compatibles')) {
                    $skipped++;
                } else {
                    $failed++;
                }
            }
        }

        // Propagar UNA vez con los ítems tocados de todo el lote (N8): un
        // ancestro compartido por varias líneas se recalcula una sola vez en
        // vez de una vez por línea que lo afecta.
        if ($touchedIngredientIds !== [] || $touchedPackagingIds !== []) {
            $recipeIds = array_merge(
                $this->propagator->recipeIdsUsingIngredients(array_values(array_unique($touchedIngredientIds))),
                $this->propagator->recipeIdsUsingPackagings(array_values(array_unique($touchedPackagingIds))),
            );
            $this->propagator->propagateManyFrom($recipeIds);
        }

        $message = match (true) {
            $applied > 0 && $skipped > 0 => "{$applied} renglón(es) aplicado(s). {$skipped} requieren especificar el divisor manualmente.",
            $applied > 0 => "{$applied} renglón(es) asociado(s) y costos actualizados.",
            $skipped > 0 => 'Las sugerencias de IA requieren completar el divisor de unidades — aplicalas una por una desde cada renglón.',
            default => 'No se pudieron aplicar las sugerencias.',
        };

        if ($failed > 0) {
            $message .= " {$failed} fallaron inesperadamente.";
        }

        $flashKey = ($applied > 0 && $failed === 0) ? 'status' : 'error';

        return back()->with($flashKey, $message);
    }
}
