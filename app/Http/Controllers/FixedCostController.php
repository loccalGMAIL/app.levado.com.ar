<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedCostRequest;
use App\Http\Requests\UpdateFixedCostRequest;
use App\Models\FixedCost;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class FixedCostController extends Controller
{
    public function index(): View
    {
        $tenant = app(Tenant::class);
        $fixedCosts = $tenant->fixedCosts()
            ->with('category')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $totalActive = $tenant->fixedCosts()->where('active', true)->sum('monthly_amount');
        $categories = $tenant->fixedCostCategories()->orderBy('name')->get();
        $showCategories = session('reopen_categories', false);

        return view('fixed-costs.index', compact('fixedCosts', 'totalActive', 'categories', 'showCategories'));
    }

    public function store(StoreFixedCostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $fixedCost = app(Tenant::class)->fixedCosts()->create(Arr::except($data, ['valid_from']));

        $fixedCost->logs()->create([
            'monthly_amount' => $fixedCost->monthly_amount,
            'valid_from' => $data['valid_from'],
        ]);

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

        return redirect()->route('fixed-costs.index')->with('status', 'Gasto fijo actualizado.');
    }

    public function toggleActive(FixedCost $fixedCost): RedirectResponse
    {
        $this->authorizeFixedCost($fixedCost);

        $fixedCost->update(['active' => ! $fixedCost->active]);

        $label = $fixedCost->active ? 'activado' : 'desactivado';

        return back()->with('status', "Gasto fijo {$label}.");
    }

    private function authorizeFixedCost(FixedCost $fixedCost): void
    {
        abort_unless($fixedCost->tenant_id === app(Tenant::class)->id, 403);
    }
}
