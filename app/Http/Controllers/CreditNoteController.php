<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Http\Requests\StoreCreditNoteLineRequest;
use App\Http\Requests\StoreCreditNoteRequest;
use App\Http\Requests\UpdateCreditNoteLineRequest;
use App\Http\Requests\UpdateCreditNoteRequest;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\CreditNoteLineRecorder;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly CreditNoteLineRecorder $lineRecorder,
        private readonly StockService $stock,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortCol = request('sort', 'date');
        $sortDir = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $tenant->creditNotes()
            ->with(['supplier', 'purchase'])
            ->withCount('lines')
            ->withSum('lines as net_total', 'subtotal')
            ->when(request('search'), function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('note_number', 'like', "%{$s}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$s}%"));
                });
            })
            ->when(request('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));

        match ($sortCol) {
            'note_number' => $query->orderBy('note_number', $sortDir)->orderByDesc('credit_notes.id'),
            'supplier' => $query->join('suppliers', 'suppliers.id', '=', 'credit_notes.supplier_id')
                ->select('credit_notes.*')
                ->orderBy('suppliers.name', $sortDir)
                ->orderByDesc('credit_notes.id'),
            'total' => $query->orderBy('net_total', $sortDir)->orderByDesc('id'),
            default => $query->orderBy('note_date', $sortDir)->orderByDesc('id'),
        };

        $creditNotes = $query->paginate(20)->withQueryString();
        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();
        // Acota el picker del modal de alta a compras recientes: elegir la compra
        // de origen entre miles de facturas históricas no es un caso de uso real.
        $purchases = $tenant->purchases()->with('supplier')->latest('invoice_date')->limit(200)->get();

        // La acción «Nota de crédito» de cada renglón de /purchases llega acá con
        // ?purchase_id=X: precarga y bloquea el modal de alta contra esa compra,
        // en vez de obligar a volver a buscarla en el select general.
        $lockedPurchase = request()->filled('purchase_id')
            ? $tenant->purchases()->with('supplier')->find(request()->integer('purchase_id'))
            : null;

        return view('credit-notes.index', compact('creditNotes', 'suppliers', 'purchases', 'lockedPurchase'));
    }

    public function store(StoreCreditNoteRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        abort_unless(
            $tenant->suppliers()->where('id', $request->validated('supplier_id'))->exists(),
            403,
        );

        $creditNote = $tenant->creditNotes()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'credit_note',
            targetId: $creditNote->id,
            action: 'credit_note.created',
            payload: ['note_number' => $creditNote->note_number],
            tenantId: $tenant->id,
        );

        return redirect()->route('credit-notes.show', $creditNote)->with('status', 'Nota de crédito registrada.');
    }

    public function update(UpdateCreditNoteRequest $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);
        $tenant = app(Tenant::class);

        abort_unless(
            $tenant->suppliers()->where('id', $request->validated('supplier_id'))->exists(),
            403,
        );

        $creditNote->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'credit_note',
            targetId: $creditNote->id,
            action: 'credit_note.updated',
            payload: ['note_number' => $creditNote->note_number],
            tenantId: $tenant->id,
        );

        return redirect()->route('credit-notes.show', $creditNote)->with('status', 'Nota de crédito actualizada.');
    }

    public function show(CreditNote $creditNote): View
    {
        $this->authorize('view', $creditNote);
        $tenant = app(Tenant::class);

        $creditNote->load(['supplier', 'purchase', 'lines.purchaseLine']);

        $suppliers = $tenant->suppliers()->orderBy('name')->get();
        $units = Unit::cases();

        // Renglones aplicados de la compra de origen, con lo que ya se devolvió
        // de cada uno descontado: son las opciones válidas para un renglón nuevo.
        $availablePurchaseLines = $creditNote->purchase
            ? $creditNote->purchase->lines()
                ->whereNotNull('cost_applied_at')
                ->whereNull('excluded_at')
                ->get()
            : collect();

        return view('credit-notes.show', compact('creditNote', 'suppliers', 'units', 'availablePurchaseLines'));
    }

    public function destroy(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('delete', $creditNote);

        $noteNumber = $creditNote->note_number;
        $tenantId = $creditNote->tenant_id;
        $creditNoteId = $creditNote->id;

        // Revertir el stock de los renglones aplicados ANTES del delete: el cascade
        // de la FK borra los renglones y perderíamos la referencia para el contramovimiento.
        DB::transaction(function () use ($creditNote) {
            $creditNote->lines()
                ->whereNotNull('stock_applied_at')
                ->get()
                ->each(fn (CreditNoteLine $line) => $this->stock->reverseCreditNoteLineExit($line, request()->user()));

            $creditNote->delete();
        });

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'credit_note',
            targetId: $creditNoteId,
            action: 'credit_note.deleted',
            payload: ['note_number' => $noteNumber],
            tenantId: $tenantId,
        );

        return redirect()->route('credit-notes.index')->with('status', 'Nota de crédito eliminada.');
    }

    public function storeLine(StoreCreditNoteLineRequest $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        $line = $this->lineRecorder->storeLine($creditNote, $request->validated(), $request->user());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'credit_note_line',
            targetId: $line->id,
            action: 'credit_note_line.created',
            payload: ['purchase_line_id' => $line->purchase_line_id],
            tenantId: $creditNote->tenant_id,
        );

        return back()->with('status', 'Renglón agregado.');
    }

    public function updateLine(UpdateCreditNoteLineRequest $request, CreditNote $creditNote, CreditNoteLine $line): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        $this->lineRecorder->recompute($line, $request->validated(), $request->user());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'credit_note_line',
            targetId: $line->id,
            action: 'credit_note_line.updated',
            payload: ['purchase_line_id' => $line->purchase_line_id],
            tenantId: $creditNote->tenant_id,
        );

        return back()->with('status', 'Renglón actualizado.');
    }

    public function destroyLine(CreditNote $creditNote, CreditNoteLine $line): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        DB::transaction(function () use ($line) {
            if ($line->isStockApplied()) {
                $this->stock->reverseCreditNoteLineExit($line, request()->user());
            }

            $line->delete();
        });

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'credit_note_line',
            targetId: $line->id,
            action: 'credit_note_line.deleted',
            payload: ['purchase_line_id' => $line->purchase_line_id],
            tenantId: $creditNote->tenant_id,
        );

        return back()->with('status', 'Renglón eliminado.');
    }
}
