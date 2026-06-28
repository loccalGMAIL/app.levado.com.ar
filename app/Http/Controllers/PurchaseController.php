<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Http\Requests\StorePurchaseLineRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseLineRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\PurchaseLineRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly PurchaseLineRecorder $lineRecorder,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortCol = request('sort', 'date');
        $sortDir = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $tenant->purchases()
            ->with('supplier')
            ->withCount('lines')
            ->withCount(['lines as applied_count' => fn ($q) => $q->whereNotNull('cost_applied_at')])
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

        $purchase = $tenant->purchases()->create($request->validated());

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

        $purchase->update($request->validated());

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

        $ingredients = $tenant->ingredients()->active()->orderBy('name')->get();
        $packagings = $tenant->packagings()->active()->orderBy('name')->get();
        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();
        $units = Unit::cases();

        return view('purchases.show', compact('purchase', 'ingredients', 'packagings', 'suppliers', 'units'));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);

        $invoiceNumber = $purchase->invoice_number;
        $tenantId = $purchase->tenant_id;
        $purchaseId = $purchase->id;
        $imagePath = $purchase->invoice_image_path;

        $purchase->delete(); // lines cascade via FK

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
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
        abort_unless($line->purchase_id === $purchase->id, 403);

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

        $purchase->load('lines');
        $totalSubtotal = $purchase->lines->sum(fn ($l) => (float) $l->subtotal);
        $totalIva = $purchase->lines->sum(fn ($l) => (float) $l->subtotal * (float) $l->iva_rate);
        $totalPercepcion = $purchase->lines->sum(fn ($l) => (float) $l->subtotal * ((float) ($l->percepcion_rate ?? 0) / 100));

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

    public function invoiceImage(Purchase $purchase): StreamedResponse
    {
        $this->authorize('update', $purchase);

        abort_if(
            blank($purchase->invoice_image_path) || ! Storage::disk('public')->exists($purchase->invoice_image_path),
            404,
        );

        // Served through the app (not the /storage symlink) so it works on any host.
        return Storage::disk('public')->response($purchase->invoice_image_path);
    }

    public function updateLine(UpdatePurchaseLineRequest $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorize('update', $purchase);
        abort_unless($line->purchase_id === $purchase->id, 403);

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
        abort_unless($line->purchase_id === $purchase->id, 403);

        $line->delete();

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

        $ingredients = $tenant->ingredients()->active()->orderBy('name')->get();
        $packagings = $tenant->packagings()->active()->orderBy('name')->get();

        // Catalog keyed by id for fast Alpine.js lookup.
        $ingredientCatalog = $ingredients->keyBy('id')->map(fn ($i) => [
            'unit' => $i->unit->value,
            'name' => $i->name,
            'subdivisions' => $i->subdivisions,
            'subdivisionLabel' => $i->subdivision_label,
        ])->toArray();

        return view('purchases.match', compact('purchase', 'ingredients', 'packagings', 'ingredientCatalog'));
    }

    /**
     * Phase 2: associate a captured line with a catalog item and impute its cost.
     * Accepts an optional unit_cost override (used when units are incompatible and
     * the user provides the cost-per-catalog-unit directly from the match view).
     */
    public function matchLine(Request $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorize('update', $purchase);
        abort_unless($line->purchase_id === $purchase->id, 403);

        $validated = $request->validate([
            'match' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $match = $validated['match'] ?? null;
        $unitCost = isset($validated['unit_cost']) && $validated['unit_cost'] !== ''
            ? (float) $validated['unit_cost']
            : null;

        // "— sin asociar —": mark the line as pending again (does not revert costs).
        if (blank($match)) {
            $line->update(['purchaseable_type' => null, 'purchaseable_id' => null, 'cost_applied_at' => null]);

            return back()->with('status', 'Renglón marcado como pendiente.');
        }

        [$type, $id] = array_pad(explode(':', $match, 2), 2, null);
        abort_unless(in_array($type, ['ingredient', 'packaging'], true) && is_numeric($id), 422);

        $tenant = app(Tenant::class);
        $belongs = $type === 'ingredient'
            ? $tenant->ingredients()->whereKey($id)->exists()
            : $tenant->packagings()->whereKey($id)->exists();
        abort_unless($belongs, 422);

        try {
            DB::transaction(function () use ($line, $type, $id, $unitCost) {
                $line->update(['purchaseable_type' => $type, 'purchaseable_id' => (int) $id]);
                if ($unitCost !== null) {
                    $this->lineRecorder->applyWithCost($line, $unitCost);
                } else {
                    $this->lineRecorder->apply($line);
                }
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

        $pending = $purchase->lines()
            ->whereNotNull('purchaseable_id')
            ->whereNull('cost_applied_at')
            ->get();

        foreach ($pending as $line) {
            $line->setRelation('purchase', $purchase);
            try {
                DB::transaction(fn () => $this->lineRecorder->apply($line));
                $applied++;
            } catch (HttpException $e) {
                if (str_contains($e->getMessage(), 'unidades no son compatibles')) {
                    $skipped++;
                } else {
                    $failed++;
                }
            }
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
