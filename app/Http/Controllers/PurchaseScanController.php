<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Enums\Unit;
use App\Http\Requests\StoreScannedPurchaseRequest;
use App\Models\Purchase;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\InvoiceExtractor;
use App\Services\InvoiceImagePreparer;
use App\Services\PurchaseLineRecorder;
use App\Services\SupplierMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseScanController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly InvoiceExtractor $extractor,
        private readonly InvoiceImagePreparer $preparer,
        private readonly PurchaseLineRecorder $lineRecorder,
        private readonly SupplierMatcher $supplierMatcher,
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

        $ingredients = $tenant->ingredients()->active()->orderBy('name')->get();
        $packagings = $tenant->packagings()->active()->orderBy('name')->get();

        if ($ingredients->isEmpty() && $packagings->isEmpty()) {
            return back()->with('error', 'Primero cargá tus insumos o descartables para poder asociar los ítems de la factura.');
        }

        $contents = (string) file_get_contents($file->getRealPath());
        [$contents, $mime] = $this->preparer->prepare($contents, $file->getMimeType() ?? 'image/jpeg');

        $extension = $mime === 'application/pdf' ? 'pdf' : 'jpg';
        $path = "purchases/{$tenant->id}/".Str::uuid()->toString().'.'.$extension;
        // Disco privado: los comprobantes son datos fiscales, ver PurchaseController::storeInvoiceImage().
        Storage::disk('local')->put($path, $contents);

        try {
            $draft = $this->extractor->extract(
                base64_encode($contents),
                $mime,
                $ingredients,
                $packagings,
            );
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            Log::error('invoice scan: extraction failed', ['error' => $e->getMessage(), 'exception' => $e::class]);

            return back()->with('error', $e->getMessage());
        }

        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();
        $matchedSupplierId = $this->supplierMatcher->match($draft['header']['supplier_name'] ?? null, $suppliers);
        $invoiceNumber = $draft['header']['invoice_number'] ?? null;

        $possibleDuplicate = null;
        if (filled($invoiceNumber) && $matchedSupplierId !== null) {
            $possibleDuplicate = Purchase::where('tenant_id', $tenant->id)
                ->where('supplier_id', $matchedSupplierId)
                ->where('invoice_number', $invoiceNumber)
                ->first();
        }

        return view('purchases.scan.review', [
            'draft' => $draft,
            'imagePath' => $path,
            'matchedSupplierId' => $matchedSupplierId,
            'suppliers' => $suppliers,
            'units' => Unit::cases(),
            'possibleDuplicate' => $possibleDuplicate,
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
                'default_iva_rate' => $data['default_iva_rate'] ?? null,
                'default_percepcion_rate' => $data['default_percepcion_rate'] ?? null,
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
                    'percepcion_rate' => $row['percepcion_rate'] ?? null,
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
        $type = CatalogItemType::tryFrom((string) ($row['matched_type'] ?? ''));
        $id = is_numeric($row['matched_id'] ?? null) ? (int) $row['matched_id'] : null;

        $valid = ($type === CatalogItemType::Ingredient && in_array($id, $ingredientIds, true))
            || ($type === CatalogItemType::Packaging && in_array($id, $packagingIds, true));

        return $valid ? [$type->value, $id] : [null, null];
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

        // 'public' cubre escaneos hechos antes de la migración al disco privado.
        return Storage::disk('local')->exists($path) || Storage::disk('public')->exists($path)
            ? $path
            : null;
    }
}
