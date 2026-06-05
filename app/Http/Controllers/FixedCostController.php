<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedCostRequest;
use App\Http\Requests\UpdateFixedCostRequest;
use App\Models\FixedCost;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class FixedCostController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortable = ['name', 'monthly_amount'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $fixedCosts = $tenant->fixedCosts()
            ->with('category')
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        $totalActive = $tenant->fixedCosts()->where('active', true)->sum('monthly_amount');
        $categories = $tenant->fixedCostCategories()->orderBy('name')->get();
        $showCategories = session('reopen_categories', false);

        return view('fixed-costs.index', compact('fixedCosts', 'totalActive', 'categories', 'showCategories'));
    }

    public function store(StoreFixedCostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tenant = app(Tenant::class);
        $fixedCost = $tenant->fixedCosts()->create(Arr::except($data, ['valid_from']));

        $fixedCost->logs()->create([
            'monthly_amount' => $fixedCost->monthly_amount,
            'valid_from' => $data['valid_from'],
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'fixed_cost',
            targetId: $fixedCost->id,
            action: 'fixed_cost.created',
            payload: ['name' => $fixedCost->name],
            tenantId: $tenant->id,
        );

        return redirect()->route('fixed-costs.index')->with('status', 'Gasto fijo creado.');
    }

    public function update(UpdateFixedCostRequest $request, FixedCost $fixedCost): RedirectResponse
    {
        $this->authorizeFixedCost($fixedCost);

        $data = $request->validated();

        if ((float) $fixedCost->monthly_amount !== (float) $data['monthly_amount']) {
            $fixedCost->logs()->create([
                'monthly_amount' => $data['monthly_amount'],
                'valid_from' => $data['valid_from'],
            ]);
        }

        $fixedCost->update(Arr::except($data, ['valid_from']));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'fixed_cost',
            targetId: $fixedCost->id,
            action: 'fixed_cost.updated',
            payload: ['name' => $fixedCost->name],
            tenantId: $fixedCost->tenant_id,
        );

        return redirect()->route('fixed-costs.index')->with('status', 'Gasto fijo actualizado.');
    }

    public function toggleActive(FixedCost $fixedCost): RedirectResponse
    {
        $this->authorizeFixedCost($fixedCost);

        $fixedCost->update(['active' => ! $fixedCost->active]);
        $action = $fixedCost->active ? 'fixed_cost.activated' : 'fixed_cost.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'fixed_cost',
            targetId: $fixedCost->id,
            action: $action,
            payload: ['name' => $fixedCost->name],
            tenantId: $fixedCost->tenant_id,
        );

        $label = $fixedCost->active ? 'activado' : 'desactivado';

        return back()->with('status', "Gasto fijo {$label}.");
    }

    private function authorizeFixedCost(FixedCost $fixedCost): void
    {
        abort_unless($fixedCost->tenant_id === app(Tenant::class)->id, 403);
    }
}
