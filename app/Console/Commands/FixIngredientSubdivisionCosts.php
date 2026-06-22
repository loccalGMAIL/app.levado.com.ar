<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Packaging;
use App\Services\RecipeCostPropagator;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class FixIngredientSubdivisionCosts extends Command
{
    protected $signature = 'ingredients:fix-subdivision-costs
                            {--type=all : Qué tipo corregir: ingredient, packaging, all}';

    protected $description = 'Corrige el costo de ingredientes/envases con subdivisión creados manualmente (cost_per_package nulo).';

    public function __construct(private readonly RecipeCostPropagator $propagator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');

        $ingredients = collect();
        $packagings = collect();

        if (in_array($type, ['all', 'ingredient'])) {
            $ingredients = Ingredient::whereNotNull('subdivisions')
                ->whereNull('cost_per_package')
                ->get();
        }

        if (in_array($type, ['all', 'packaging'])) {
            $packagings = Packaging::whereNotNull('subdivisions')
                ->whereNull('cost_per_package')
                ->get();
        }

        $total = $ingredients->count() + $packagings->count();

        if ($total === 0) {
            info('No hay ingredientes ni envases pendientes de corregir.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($ingredients as $item) {
            $rows[] = [
                'Tipo' => 'Ingrediente',
                'Nombre' => $item->name,
                'Subdivisiones' => $item->subdivisions.' '.($item->subdivision_label ?? 'u.').' / envase',
                'Costo actual' => '$ '.number_format($item->cost_per_unit, 2),
                '→ Por envase' => '$ '.number_format($item->cost_per_unit, 2),
                '→ Sub-unidad' => '$ '.number_format($item->cost_per_unit / $item->subdivisions, 4),
            ];
        }

        foreach ($packagings as $item) {
            $rows[] = [
                'Tipo' => 'Envase',
                'Nombre' => $item->name,
                'Subdivisiones' => $item->subdivisions.' '.($item->subdivision_label ?? 'u.').' / presentación',
                'Costo actual' => '$ '.number_format($item->cost_per_unit, 2),
                '→ Por envase' => '$ '.number_format($item->cost_per_unit, 2),
                '→ Sub-unidad' => '$ '.number_format($item->cost_per_unit / $item->subdivisions, 4),
            ];
        }

        warning("Se encontraron {$total} ítem(s) con subdivisión y sin costo de envase registrado.");
        $this->newLine();
        table(
            headers: ['Tipo', 'Nombre', 'Subdivisiones', 'Costo actual', '→ Por envase', '→ Sub-unidad'],
            rows: $rows,
        );
        $this->newLine();
        info('El "costo actual" se asume como precio del ENVASE y se dividirá entre las subdivisiones.');

        if (! confirm('¿Confirmar la corrección de todos los ítems listados?', default: false)) {
            info('Operación cancelada.');

            return self::SUCCESS;
        }

        $fixed = 0;

        foreach ($ingredients as $item) {
            $packagePrice = (float) $item->cost_per_unit;
            $subUnitPrice = $packagePrice / $item->subdivisions;

            $item->priceLogs()->create([
                'cost_per_unit' => $subUnitPrice,
                'recorded_at' => now(),
            ]);

            $item->update([
                'cost_per_package' => $packagePrice,
                'cost_per_unit' => $subUnitPrice,
            ]);

            $this->propagator->propagateFromIngredient($item->id);
            $fixed++;
        }

        foreach ($packagings as $item) {
            $packagePrice = (float) $item->cost_per_unit;
            $subUnitPrice = $packagePrice / $item->subdivisions;

            $item->priceLogs()->create([
                'cost_per_unit' => $subUnitPrice,
                'recorded_at' => now(),
            ]);

            $item->update([
                'cost_per_package' => $packagePrice,
                'cost_per_unit' => $subUnitPrice,
            ]);

            $this->propagator->propagateFromPackaging($item->id);
            $fixed++;
        }

        info("Se corrigieron {$fixed} ítem(s) correctamente. Los costos de recetas fueron actualizados.");

        return self::SUCCESS;
    }
}
