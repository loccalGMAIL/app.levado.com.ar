<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCustomerOrderWeekRequest;
use App\Models\Customer;
use App\Models\CustomerOrderLine;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\CustomerOrderWeekService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reparto › Pedidos: lo que pide cada cliente, en una grilla semanal (lunes a
 * domingo) por cliente. Lo pedido suma en la planilla de Pedidos que tenga
 * asignada cada artículo (ver CustomerOrderTotals) y arma la hoja del
 * repartidor.
 */
class CustomerOrderWeekController extends Controller
{
    public function __construct(private readonly CustomerOrderWeekService $weeks) {}

    public function index(Request $request): View
    {
        $tenant = app(Tenant::class);

        $customers = $tenant->customers()->active()->with('deliveryPerson')->orderBy('name')->get();
        $customer = $customers->firstWhere('id', $request->integer('customer')) ?? $customers->first();

        $start = $this->weeks->mondayFromInput($request->string('start')->toString());
        $week = $customer !== null ? $this->weeks->weekFor($customer, $start) : null;

        $lines = $week !== null
            ? $week->lines->map(fn (CustomerOrderLine $line) => [
                ...$this->weeks->presentLine($line),
                'no_destination' => $line->product->order_sheet_id === null,
            ])->values()->all()
            : [];

        $products = Product::query()->producible()->orderBy('name')
            ->with('recipe')
            ->get(['id', 'name', 'unit', 'recipe_id', 'recipe_quantity', 'order_sheet_id'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit->short(),
                'container' => $product->containerPayload(),
                'no_destination' => $product->order_sheet_id === null,
            ])->values()->all();

        return view('reparto.pedidos', [
            'customers' => $customers,
            'customerGroups' => $customers->groupBy(fn (Customer $c) => $c->delivery_person_id === null ? 'Sin repartidor' : $c->deliveryPerson->name),
            'deliveryPeople' => $tenant->deliveryPeople()->active()->orderBy('name')->get(),
            'customer' => $customer,
            'start' => $start,
            'days' => collect(range(0, 6))->map(fn (int $offset) => $start->addDays($offset)),
            'lines' => $lines,
            'products' => $products,
            'hasWeek' => $week !== null,
            'canEdit' => Gate::allows('manage-costs'),
        ]);
    }

    public function update(SaveCustomerOrderWeekRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $week = $this->weeks->ensureWeek($customer, CarbonImmutable::parse($request->validated('start')));

        /** @var array<int, array{product_id: int, quantities: array<int, float|int|string|null>}> $lines */
        $lines = $request->validated('lines');

        $this->weeks->save($week, $lines);

        return $this->backToWeek($customer, $week->starts_on->toDateString(), 'Semana guardada.');
    }

    public function recopy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate(['start' => ['required', 'date']]);

        $week = $this->weeks->ensureWeek($customer, CarbonImmutable::parse($validated['start']));
        $this->weeks->recopy($week);

        return $this->backToWeek($customer, $week->starts_on->toDateString(), 'Se copió la semana anterior.');
    }

    private function backToWeek(Customer $customer, string $start, string $status): RedirectResponse
    {
        return redirect()
            ->route('reparto.pedidos.index', ['customer' => $customer->id, 'start' => $start])
            ->with('status', $status);
    }
}
