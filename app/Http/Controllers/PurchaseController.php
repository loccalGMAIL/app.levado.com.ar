<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Http\Requests\StorePurchaseLineRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseLineRequest;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\PurchaseLineRecorder;
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

        $purchases = $tenant->purchases()
            ->with('supplier')
            ->withCount('lines')
            ->withSum('lines as net_total', 'subtotal')
            ->when(request('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(request('from'), fn ($q, $date) => $q->where('invoice_date', '>=', $date))
            ->when(request('to'), fn ($q, $date) => $q->where('invoice_date', '<=', $date))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();
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

    public function show(Purchase $purchase): View
    {
        $this->authorizePurchase($purchase);
        $tenant = app(Tenant::class);

        $purchase->load(['supplier', 'lines']);

        $ingredients = $tenant->ingredients()->where('active', true)->orderBy('name')->get();
        $packagings = $tenant->packagings()->where('active', true)->orderBy('name')->get();
        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();
        $units = Unit::cases();

        return view('purchases.show', compact('purchase', 'ingredients', 'packagings', 'suppliers', 'units'));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchase($purchase);

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

    public function storeLine(StorePurchaseLineRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchase($purchase);

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
        $this->authorizePurchase($purchase);

        abort_if(
            blank($purchase->invoice_image_path) || ! Storage::disk('public')->exists($purchase->invoice_image_path),
            404,
        );

        // Served through the app (not the /storage symlink) so it works on any host.
        return Storage::disk('public')->response($purchase->invoice_image_path);
    }

    public function updateLine(UpdatePurchaseLineRequest $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorizePurchase($purchase);
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
        $this->authorizePurchase($purchase);
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

    /**
     * Phase 2: associate a captured line with a catalog item and impute its cost.
     */
    public function matchLine(Request $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorizePurchase($purchase);
        abort_unless($line->purchase_id === $purchase->id, 403);

        $match = $request->validate(['match' => ['nullable', 'string']])['match'] ?? null;

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
            DB::transaction(function () use ($line, $type, $id) {
                $line->update(['purchaseable_type' => $type, 'purchaseable_id' => (int) $id]);
                $this->lineRecorder->apply($line);
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
        $this->authorizePurchase($purchase);

        $applied = 0;
        $failed = 0;

        $pending = $purchase->lines()
            ->whereNotNull('purchaseable_id')
            ->whereNull('cost_applied_at')
            ->get();

        foreach ($pending as $line) {
            try {
                DB::transaction(fn () => $this->lineRecorder->apply($line));
                $applied++;
            } catch (HttpException) {
                $failed++;
            }
        }

        $message = "{$applied} renglón(es) asociado(s) y costos actualizados.";
        if ($failed > 0) {
            $message .= " {$failed} no se pudieron aplicar — revisá las unidades.";
        }

        return back()->with($failed > 0 ? 'error' : 'status', $message);
    }

    private function authorizePurchase(Purchase $purchase): void
    {
        abort_unless($purchase->tenant_id === app(Tenant::class)->id, 403);
    }
}
