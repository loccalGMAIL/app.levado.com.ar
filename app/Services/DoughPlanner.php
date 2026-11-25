<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Models\Location;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockLevel;
use App\Rules\TrayQuantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Planilla de amasado de un día D: qué amasar y cuánta masa preparar.
 *
 * - Lo que pasa por cámara se amasa el día anterior: se toma lo pedido para
 *   D+1 menos lo que ya hay en cámara (piso 0).
 * - Lo directo se amasa y hornea el mismo día: lo pedido para D.
 *
 * Por masa: cada sub-receta de PRIMER nivel de las recetas a amasar, con el
 * total a preparar en su unidad de rinde. No usa RecipeExploder (que aplana
 * las sub-recetas hasta sus insumos): acá interesa la masa, no la harina.
 *
 * Solo lee. Devuelve arrays y escalares: la vista se renderiza también para
 * dompdf.
 */
class DoughPlanner
{
    public function __construct(
        private readonly DailyDemand $demand,
        private readonly UnitConverter $converter,
        private readonly ChamberClosingService $closings,
    ) {}

    /**
     * @return array{
     *     date: CarbonImmutable,
     *     chamber_date: CarbonImmutable,
     *     chamber: list<array<string, mixed>>,
     *     direct: list<array<string, mixed>>,
     *     masses: list<array{recipe_id: int, name: string, quantity: float, unit: string, articles: list<string>}>,
     *     without_mass: list<string>,
     *     last_closing: array{at: CarbonImmutable, same_day: bool}|null,
     * }
     */
    public function forDate(Location $kitchen, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $chamberDate = $date->addDay();
        $chamberStock = $this->chamberStock($kitchen);

        $chamberRows = $this->demand->forDate($kitchen->tenant_id, $chamberDate)
            ->filter(fn (array $entry) => $entry['product']->usesProofing())
            ->map(fn (array $entry) => $this->row($entry['product'], $entry['units'], $chamberStock[$entry['product']->id] ?? 0.0, true))
            ->values()
            ->all();

        $directRows = $this->demand->forDate($kitchen->tenant_id, $date)
            ->reject(fn (array $entry) => $entry['product']->usesProofing())
            ->map(fn (array $entry) => $this->row($entry['product'], $entry['units'], 0.0, false))
            ->values()
            ->all();

        [$masses, $withoutMass] = $this->masses(array_merge($chamberRows, $directRows));

        return [
            'date' => $date,
            'chamber_date' => $chamberDate,
            'chamber' => $chamberRows,
            'direct' => $directRows,
            'masses' => $masses,
            'without_mass' => $withoutMass,
            'last_closing' => $this->lastClosing($kitchen, $date),
        ];
    }

    /**
     * Unidades a amasar de cada artículo base del día (las dos tablas
     * juntas), por id: lo que usa el amasado para crear su borrador.
     *
     * @return array<int, array{product_id: int, goes_to_chamber: bool, to_make: float}>
     */
    public function plannedUnits(Location $kitchen, CarbonImmutable $date): array
    {
        $plan = $this->forDate($kitchen, $date);

        return collect(array_merge($plan['chamber'], $plan['direct']))
            ->mapWithKeys(fn (array $row) => [$row['product_id'] => [
                'product_id' => $row['product_id'],
                'goes_to_chamber' => $row['goes_to_chamber'],
                'to_make' => $row['to_make'],
            ]])
            ->all();
    }

    /**
     * @return array{product_id: int, recipe_id: int, name: string, unit: string, goes_to_chamber: bool, ordered: float, in_chamber: float, to_make: float, container: array{type: string, label: string, short: string, per: float}|null, containers: float|null}
     */
    private function row(Product $product, float $ordered, float $inChamber, bool $goesToChamber): array
    {
        $toMake = max(0.0, round($ordered - $inChamber, 3));
        $container = $product->containerPayload();

        return [
            'product_id' => $product->id,
            'recipe_id' => (int) $product->recipe_id,
            'name' => $product->name,
            'unit' => $product->unit->short(),
            'goes_to_chamber' => $goesToChamber,
            'ordered' => round($ordered, 3),
            'in_chamber' => round($inChamber, 3),
            'to_make' => $toMake,
            'container' => $container,
            'containers' => $container !== null && $container['per'] > 0
                ? TrayQuantity::ceilToStep($toMake / $container['per'])
                : null,
        ];
    }

    /**
     * Cuándo se cerró la cámara por última vez y si fue el día de la
     * planilla: el orden del día es cierre de cámara → planilla → amasado,
     * así que sin cierre ese día lo «en cámara» puede estar desactualizado.
     * Solo avisa; no bloquea.
     *
     * @return array{at: CarbonImmutable, same_day: bool}|null
     */
    private function lastClosing(Location $kitchen, CarbonImmutable $date): ?array
    {
        $closing = $this->closings->lastConfirmed($kitchen->loadMissing('tenant')->tenant);

        if ($closing === null || $closing->confirmed_at === null) {
            return null;
        }

        $at = CarbonImmutable::parse($closing->confirmed_at);

        return ['at' => $at, 'same_day' => $at->isSameDay($date)];
    }

    /**
     * Stock actual en la cámara, por id de artículo. Sin cámara todavía, vacío
     * (no la crea: la planilla solo lee).
     *
     * @return array<int, float>
     */
    private function chamberStock(Location $kitchen): array
    {
        $chamber = $kitchen->loadMissing('tenant')->tenant->existingProofingChamber();

        if ($chamber === null) {
            return [];
        }

        return StockLevel::query()
            ->where('tenant_id', $kitchen->tenant_id)
            ->where('location_id', $chamber->id)
            ->where('stockable_type', CatalogItemType::Product->value)
            ->pluck('quantity', 'stockable_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }

    /**
     * Masas a preparar: por cada sub-receta de primer nivel, la suma sobre los
     * artículos de (cantidad en la receta × a amasar ÷ rinde), en la unidad de
     * rinde de la sub-receta. Los artículos que no usan ninguna van aparte.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array{recipe_id: int, name: string, quantity: float, unit: string, articles: list<string>}>, 1: list<string>}
     */
    private function masses(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => $row['to_make'] > 0));

        /** @var Collection<int, Recipe> $recipes */
        $recipes = Recipe::query()
            ->whereIn('id', array_unique(array_column($rows, 'recipe_id')))
            ->with('subrecipeLines.childRecipe')
            ->get()
            ->keyBy('id');

        $masses = [];
        $withoutMass = [];

        foreach ($rows as $row) {
            $recipe = $recipes->get($row['recipe_id']);

            if ($recipe === null || (float) $recipe->yield_quantity <= 0 || $recipe->subrecipeLines->isEmpty()) {
                $withoutMass[] = $row['name'];

                continue;
            }

            foreach ($recipe->subrecipeLines as $line) {
                $child = $line->childRecipe;
                $perBatch = $this->converter->convert((float) $line->quantity_used, $line->unit, $child->yield_unit);

                if ($perBatch === null) {
                    continue;
                }

                $masses[$child->id] ??= [
                    'recipe_id' => $child->id,
                    'name' => $child->name,
                    'quantity' => 0.0,
                    'unit' => $child->yield_unit->short(),
                    'articles' => [],
                ];
                $masses[$child->id]['quantity'] += $perBatch * $row['to_make'] / (float) $recipe->yield_quantity;
                $masses[$child->id]['articles'][] = $row['name'];
            }
        }

        $masses = array_map(function (array $mass): array {
            $mass['quantity'] = round($mass['quantity'], 3);
            $mass['articles'] = array_values(array_unique($mass['articles']));

            return $mass;
        }, $masses);

        usort($masses, fn (array $a, array $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));

        return [$masses, $withoutMass];
    }
}
