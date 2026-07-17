<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ExpenseReceiptExtractor;
use App\Services\ReceiptStorer;
use App\Services\SupplierMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Reads a receipt with the vision API and hands the draft back to the create
 * modal, which doubles as the review screen: an expense is a handful of fields,
 * so it doesn't need the dedicated review page that purchases have.
 */
class VariableExpenseScanController extends Controller
{
    public function __construct(
        private readonly ExpenseReceiptExtractor $extractor,
        private readonly ReceiptStorer $storer,
        private readonly SupplierMatcher $supplierMatcher,
    ) {}

    public function scan(Request $request): JsonResponse
    {
        $request->validate(
            ['receipt' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf', 'max:10240']],
            [],
            ['receipt' => 'comprobante'],
        );

        $tenant = app(Tenant::class);
        $file = $request->file('receipt');

        // Stored before the API call so the user keeps the receipt even if the
        // read fails; the catch below cleans it up when the draft is unusable.
        [$path, $contents, $mime] = $this->storer->storeContents(
            (string) file_get_contents($file->getRealPath()),
            $file->getMimeType() ?? 'image/jpeg',
            "variable-expenses/{$tenant->id}",
        );

        $categories = $tenant->variableExpenseCategories()->orderBy('name')->get();

        try {
            $draft = $this->extractor->extract(base64_encode($contents), $mime, $categories);
        } catch (\Throwable $e) {
            $this->storer->delete($path);
            Log::error('expense receipt scan: extraction failed', ['error' => $e->getMessage(), 'exception' => $e::class]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();

        return response()->json([
            'path' => $path,
            'name' => $draft['name'],
            'amount' => $draft['amount'],
            'expense_date' => $draft['expense_date'],
            'category_id' => $draft['category_id'],
            'supplier_id' => $this->supplierMatcher->match($draft['supplier_name'], $suppliers),
        ]);
    }
}
