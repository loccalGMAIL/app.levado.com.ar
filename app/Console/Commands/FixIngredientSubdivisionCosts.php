<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Packaging;
use App\Services\RecipeCostPropagator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class FixIngredientSubdivisionCosts extends Command
{
    protected $signature = 'ingredients:fix-subdivision-costs
                            {--type=all : Qué tipo corregir: ingredient, packaging, all}';

    protected $description = 'Corrige ingredientes/envases con subdivisión cuyo costo por sub-unidad o precio por envase quedó incoherente.';

    /**
     * Desvío relativo a partir del cual se considera que cost_per_package quedó viejo.
     * Por debajo son redondeos del usuario al cargar el costo a mano (ej. 133,30 contra
     * 133,3333), que no hay que tocar.
     */
    private const STALE_THRESHOLD = 0.01;

    public function __construct(private readonly RecipeCostPropagator $propagator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');

        $items = collect();

        if (in_array($type, ['all', 'ingredient'])) {
            $items = $items->concat($this->candidates(Ingredient::query()->whereNotNull('subdivisions')->get(), 'Ingrediente'));
        }

        if (in_array($type, ['all', 'packaging'])) {
            $items = $items->concat($this->candidates(Packaging::query()->whereNotNull('subdivisions')->get(), 'Envase'));
        }

        if ($items->isEmpty()) {
            info('No hay ingredientes ni envases pendientes de corregir.');

            return self::SUCCESS;
        }

        warning("Se encontraron {$items->count()} ítem(s) con costos de subdivisión incoherentes.");
        $this->newLine();
        table(
            headers: ['Tipo', 'Nombre', 'Subdivisiones', 'Motivo', 'Sub-unidad', 'Por envase'],
            rows: $items->map(fn (array $row) => [
                $row['kind'],
                $row['item']->name,
                $row['item']->subdivisions.' '.($row['item']->subdivision_label ?? 'u.').' / envase',
                $row['reason'],
                $this->transition((float) $row['item']->cost_per_unit, $row['costPerUnit']),
                $this->transition((float) $row['item']->cost_per_package, $row['costPerPackage']),
            ])->all(),
        );
        $this->newLine();
        info('Caso A: el costo cargado es el del envase y nunca se dividió — se divide por las subdivisiones.');
        info('Caso B: el costo por sub-unidad es correcto y el precio por envase quedó viejo — se recalcula el envase.');

        if (! confirm('¿Confirmar la corrección de todos los ítems listados?', default: false)) {
            info('Operación cancelada.');

            return self::SUCCESS;
        }

        // Un único recálculo del árbol de recetas para toda la corrección.
        $this->propagator->batch(function () use ($items) {
            foreach ($items as $row) {
                $item = $row['item'];
                $costChanged = abs((float) $item->cost_per_unit - $row['costPerUnit']) > 0.0001;

                if ($costChanged) {
                    $item->priceLogs()->create([
                        'cost_per_unit' => $row['costPerUnit'],
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'cost_per_package' => $row['costPerPackage'],
                    'cost_per_unit' => $row['costPerUnit'],
                ]);

                if ($costChanged) {
                    $item instanceof Ingredient
                        ? $this->propagator->propagateFromIngredient($item->id)
                        : $this->propagator->propagateFromPackaging($item->id);
                }
            }
        });

        info("Se corrigieron {$items->count()} ítem(s) correctamente. Los costos de recetas fueron actualizados.");

        return self::SUCCESS;
    }

    /**
     * Clasifica cada ítem con subdivisión y devuelve sólo los que hay que corregir.
     *
     * @param  Collection<int, Ingredient|Packaging>  $rows
     * @return list<array{item: Ingredient|Packaging, kind: string, reason: string, costPerUnit: float, costPerPackage: float}>
     */
    private function candidates(Collection $rows, string $kind): array
    {
        $candidates = [];

        foreach ($rows as $item) {
            $subdivisions = (int) $item->subdivisions;
            $costPerUnit = (float) $item->cost_per_unit;

            if ($subdivisions < 2) {
                continue;
            }

            // Sin precio por envase el costo cargado es, por definición, el del envase:
            // es el ítem creado a mano antes de que existiera la columna.
            if ($item->cost_per_package === null) {
                $candidates[] = [
                    'item' => $item,
                    'kind' => $kind,
                    'reason' => 'A · nunca dividido',
                    'costPerUnit' => $costPerUnit / $subdivisions,
                    'costPerPackage' => $costPerUnit,
                ];

                continue;
            }

            $costPerPackage = (float) $item->cost_per_package;

            // Caso A — se guardó el precio del envase en las dos columnas. Va antes que el
            // caso B, que también dispararía: son correcciones opuestas. Con la validación
            // min:2 sobre subdivisions, esta igualdad nunca es legítima.
            if (abs($costPerUnit - $costPerPackage) < 0.0001) {
                $candidates[] = [
                    'item' => $item,
                    'kind' => $kind,
                    'reason' => 'A · nunca dividido',
                    'costPerUnit' => $costPerPackage / $subdivisions,
                    'costPerPackage' => $costPerPackage,
                ];

                continue;
            }

            // Caso B — el costo por sub-unidad viene de la última compra y es la fuente de
            // verdad; el precio por envase quedó sin actualizar.
            $expected = $costPerPackage / $subdivisions;

            if ($expected > 0 && abs($expected - $costPerUnit) / $expected > self::STALE_THRESHOLD) {
                $candidates[] = [
                    'item' => $item,
                    'kind' => $kind,
                    'reason' => 'B · envase viejo',
                    'costPerUnit' => $costPerUnit,
                    'costPerPackage' => $costPerUnit * $subdivisions,
                ];
            }
        }

        return $candidates;
    }

    private function transition(float $from, float $to): string
    {
        if (abs($from - $to) < 0.0001) {
            return '$ '.number_format($from, 2).' (sin cambio)';
        }

        return '$ '.number_format($from, 2).' -> $ '.number_format($to, 2);
    }
}
