<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedCostPeriodRequest;
use App\Models\FixedCost;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\FixedCostHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FixedCostHistoryController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly FixedCostHistory $history,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $period = $this->resolvePeriod(request('period'));
        $isCurrentMonth = $period->equalTo(Carbon::now()->startOfMonth());

        $fixedCosts = $tenant->fixedCosts()->with('category')
            ->orderByDesc('active')->orderBy('name')
            ->get();

        $amounts = $this->history->amountsForPeriod($tenant, $period);

        $rows = $fixedCosts->map(fn (FixedCost $fixedCost) => [
            'fixedCost' => $fixedCost,
            'amount' => $amounts->get($fixedCost->id)['amount'] ?? null,
            'carried' => $amounts->get($fixedCost->id)['carried'] ?? false,
        ]);

        $total = $rows->sum('amount');
        $carriedCount = $rows->where('carried', true)->count();

        return view('fixed-costs.history', compact('period', 'isCurrentMonth', 'rows', 'total', 'carriedCount'));
    }

    public function store(StoreFixedCostPeriodRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $period = $this->resolvePeriod($request->validated('period'));
        $isCurrentMonth = $period->equalTo(Carbon::now()->startOfMonth());

        // `amounts` llega indexado por fixed_cost_id; sólo se guardan los que
        // pertenecen al tenant actual, y sólo los que realmente vinieron con
        // un valor (un campo vacío significa "seguí arrastrando", no "poné 0").
        $ownFixedCosts = $tenant->fixedCosts()->whereKey(array_keys($request->validated('amounts')))->get()->keyBy('id');
        $amounts = collect($request->validated('amounts'))->filter(fn ($amount) => $amount !== null && $amount !== '');

        DB::transaction(function () use ($ownFixedCosts, $amounts, $period, $isCurrentMonth) {
            foreach ($amounts as $fixedCostId => $amount) {
                $fixedCost = $ownFixedCosts->get((int) $fixedCostId);

                if (! $fixedCost) {
                    continue;
                }

                $this->history->record($fixedCost, $period, (float) $amount);

                if ($isCurrentMonth) {
                    $fixedCost->update(['monthly_amount' => $amount]);
                }
            }
        });

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'fixed_cost',
            targetId: $tenant->id,
            action: 'fixed_cost.period_recorded',
            payload: ['period' => $period->format('Y-m'), 'count' => $amounts->count()],
            tenantId: $tenant->id,
        );

        return redirect()->route('fixed-costs.history', ['period' => $period->format('Y-m')])
            ->with('status', 'Gastos de '.FixedCostHistory::periodLabel($period).' guardados.');
    }

    public function show(FixedCost $fixedCost): View
    {
        $this->authorize('view', $fixedCost);

        $timeline = $this->history->timelineFor($fixedCost)->reverse()->values();

        return view('fixed-costs.cost-history', compact('fixedCost', 'timeline'));
    }

    private function resolvePeriod(?string $period): Carbon
    {
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return Carbon::now()->startOfMonth();
    }
}
