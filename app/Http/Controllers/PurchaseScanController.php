<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Http\Requests\StoreScannedPurchaseRequest;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\InvoiceExtractor;
use App\Services\PurchaseLineRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseScanController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly InvoiceExtractor $extractor,
        private readonly PurchaseLineRecorder $lineRecorder,
    ) {}

    public function create(): View
    {
        return view('purchases.scan.create');
    }

    public function scan(Request $request): View|RedirectResponse
    {
        $request->validate(
            ['invoice' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf', 'max:10240']],
            [],
            ['invoice' => 'factura'],
        );

        $tenant = app(Tenant::class);
        $file = $request->file('invoice');

        $ingredients = $tenant->ingredients()->where('active', true)->orderBy('name')->get();
        $packagings = $tenant->packagings()->where('active', true)->orderBy('name')->get();

        if ($ingredients->isEmpty() && $packagings->isEmpty()) {
            return back()->with('error', 'Primero cargá tus insumos o descartables para poder asociar los ítems de la factura.');
        }

        // Invoices are sensitive financial documents: keep them on the private
        // disk and serve them only through authenticated controller routes.
        $path = $file->store("purchases/{$tenant->id}", 'local');

        try {
            $draft = $this->extractor->extract(
                base64_encode((string) Storage::disk('local')->get($path)),
                $file->getMimeType() ?? 'image/jpeg',
                $ingredients,
                $packagings,
            );
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            Log::error('invoice scan: extraction failed', ['error' => $e->getMessage(), 'exception' => $e::class]);

            return back()->with('error', $e->getMessage());
        }

        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();

        return view('purchases.scan.review', [
            'draft' => $draft,
            'imagePath' => $path,
            'matchedSupplierId' => $this->matchSupplier($draft['header']['supplier_name'] ?? null, $suppliers),
            'suppliers' => $suppliers,
            'units' => Unit::cases(),
            // For the "se sugerirá: X" hint per line.
            'ingredientNames' => $ingredients->pluck('name', 'id'),
            'packagingNames' => $packagings->pluck('name', 'id'),
        ]);
    }

    public function store(StoreScannedPurchaseRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        abort_unless($tenant->suppliers()->where('id', $data['supplier_id'])->exists(), 403);

        $imagePath = $this->safeImagePath($data['invoice_image_path'] ?? null, $tenant);
        $ingredientIds = $tenant->ingredients()->pluck('id')->all();
        $packagingIds = $tenant->packagings()->pluck('id')->all();

        // Keep only the rows the user marked to import and that have amounts.
        $rows = collect($data['lines'] ?? [])
            ->filter(fn ($row) => ($row['include'] ?? false)
                && filled($row['quantity_purchased'] ?? null)
                && filled($row['purchase_unit'] ?? null)
                && filled($row['unit_price'] ?? null))
            ->values();

        if ($rows->isEmpty()) {
            return back()->withInput()->with('error', 'Marcá al menos un renglón para guardar la compra.');
        }

        $purchase = DB::transaction(function () use ($tenant, $data, $imagePath, $rows, $ingredientIds, $packagingIds) {
            $purchase = $tenant->purchases()->create([
                'supplier_id' => $data['supplier_id'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'invoice_total' => $data['invoice_total'] ?? null,
                'notes' => $data['notes'] ?? null,
                'invoice_image_path' => $imagePath,
            ]);

            foreach ($rows as $row) {
                [$type, $id] = $this->validSuggestion($row, $ingredientIds, $packagingIds);

                $this->lineRecorder->storePending($purchase, [
                    'raw_name' => $row['raw_name'] ?? null,
                    'purchaseable_type' => $type,
                    'purchaseable_id' => $id,
                    'quantity_purchased' => $row['quantity_purchased'],
                    'purchase_unit' => $row['purchase_unit'],
                    'unit_price' => $row['unit_price'],
                    'iva_rate' => $row['iva_rate'] ?? 0.21,
                ]);
            }

            return $purchase;
        });

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase',
            targetId: $purchase->id,
            action: 'purchase.scanned',
            payload: ['invoice_number' => $purchase->invoice_number, 'lines' => $rows->count()],
            tenantId: $tenant->id,
        );

        return redirect()->route('purchases.show', $purchase)
            ->with('status', 'Compra guardada. Ahora asociá los renglones con tus insumos para actualizar los costos.');
    }

    /**
     * Validate the AI's suggested match against the tenant catalog.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $ingredientIds
     * @param  array<int, int>  $packagingIds
     * @return array{0: ?string, 1: ?int}
     */
    private function validSuggestion(array $row, array $ingredientIds, array $packagingIds): array
    {
        $type = in_array($row['matched_type'] ?? null, ['ingredient', 'packaging'], true) ? $row['matched_type'] : null;
        $id = is_numeric($row['matched_id'] ?? null) ? (int) $row['matched_id'] : null;

        $valid = ($type === 'ingredient' && in_array($id, $ingredientIds, true))
            || ($type === 'packaging' && in_array($id, $packagingIds, true));

        return $valid ? [$type, $id] : [null, null];
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function matchSupplier(?string $name, Collection $suppliers): ?int
    {
        if (blank($name)) {
            return null;
        }

        $needle = mb_strtolower(trim($name));

        foreach ($suppliers as $supplier) {
            $hay = mb_strtolower($supplier->name);

            if ($hay === $needle || str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $supplier->id;
            }
        }

        return null;
    }

    /**
     * Only accept an image path that this tenant actually owns, to keep
     * arbitrary strings out of the database.
     */
    private function safeImagePath(?string $path, Tenant $tenant): ?string
    {
        if (blank($path) || ! str_starts_with($path, "purchases/{$tenant->id}/")) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    /**
     * Stream a freshly-scanned invoice (not yet persisted) for the review screen.
     * Only the owning tenant's images can be requested.
     */
    public function preview(Request $request): StreamedResponse
    {
        $tenant = app(Tenant::class);
        $path = $this->safeImagePath((string) $request->query('path'), $tenant);

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }
}
