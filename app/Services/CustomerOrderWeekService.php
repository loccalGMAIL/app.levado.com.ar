<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrderWeek;
use App\Services\Concerns\BuildsWeeklyGrid;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Semanas de pedidos de un cliente. Misma regla que las planillas
 * (OrderSheetWeekService): una semana nueva (actual o futura) nace como copia
 * de la última cargada de ese cliente y desde ahí se edita sola; una semana
 * pasada sin datos se muestra vacía y recién existe cuando se guarda algo.
 */
class CustomerOrderWeekService
{
    use BuildsWeeklyGrid;

    /** La semana indicada solo si ya existe; nunca crea nada. */
    public function existingWeek(Customer $customer, CarbonImmutable $date): ?CustomerOrderWeek
    {
        return $this->find($customer, $this->mondayOf($date));
    }

    /**
     * La semana pedida, o null si no existe ni corresponde crearla (una
     * semana pasada sin datos). Si es la actual o una futura y no existe, se
     * crea copiando la última semana anterior del cliente.
     */
    public function weekFor(Customer $customer, CarbonImmutable $date): ?CustomerOrderWeek
    {
        $monday = $this->mondayOf($date);

        $existing = $this->find($customer, $monday);

        if ($existing !== null) {
            return $existing;
        }

        if ($monday->lt($this->mondayOf(CarbonImmutable::now()))) {
            return null;
        }

        return $this->createFromPrevious($customer, $monday);
    }

    /** Crea (si falta) la semana indicada, también las pasadas: es lo que usa guardar. */
    public function ensureWeek(Customer $customer, CarbonImmutable $date): CustomerOrderWeek
    {
        $monday = $this->mondayOf($date);

        return $this->find($customer, $monday) ?? $this->createFromPrevious($customer, $monday);
    }

    /**
     * Reemplaza el contenido de una semana. Cada fila trae el artículo y sus
     * siete cantidades (lunes a domingo) y se guarda en la medida actual de su
     * artículo: bandejas/latas si su receta tiene rinde, unidades si no.
     *
     * @param  array<int, array{product_id: int, quantities: array<int, float|int|string|null>}>  $lines
     */
    public function save(CustomerOrderWeek $week, array $lines): void
    {
        $containers = $this->containersFor($lines);

        DB::transaction(function () use ($week, $lines, $containers) {
            $week->lines()->delete();

            foreach (array_values($lines) as $position => $line) {
                $productId = (int) $line['product_id'];
                $week->lines()->create($this->lineAttributes($position, $productId, $line['quantities'], $containers[$productId] ?? null));
            }

            $week->touch();
        });
    }

    /** Vuelve a copiar la última semana anterior sobre esta (sin anterior, la deja vacía). */
    public function recopy(CustomerOrderWeek $week): void
    {
        $week->loadMissing('customer');

        DB::transaction(function () use ($week) {
            $week->lines()->delete();
            $this->copyLines($this->previousWeek($week->customer, CarbonImmutable::parse($week->starts_on)), $week);
        });
    }

    private function find(Customer $customer, CarbonImmutable $monday): ?CustomerOrderWeek
    {
        return $customer->orderWeeks()->whereDate('starts_on', $monday->toDateString())->with('lines.product.recipe')->first();
    }

    private function previousWeek(Customer $customer, CarbonImmutable $monday): ?CustomerOrderWeek
    {
        return $customer->orderWeeks()
            ->whereDate('starts_on', '<', $monday->toDateString())
            ->orderByDesc('starts_on')
            ->with('lines')
            ->first();
    }

    private function createFromPrevious(Customer $customer, CarbonImmutable $monday): CustomerOrderWeek
    {
        try {
            $week = DB::transaction(function () use ($customer, $monday) {
                $week = $customer->orderWeeks()->create([
                    'tenant_id' => $customer->tenant_id,
                    'starts_on' => $monday->toDateString(),
                ]);

                $this->copyLines($this->previousWeek($customer, $monday), $week);

                return $week;
            });
        } catch (QueryException $exception) {
            // Doble alta simultánea: el unique (cliente, lunes) frenó a la segunda.
            $existing = $this->find($customer, $monday);

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }

        return $this->find($customer, $monday) ?? $week;
    }

    private function copyLines(?CustomerOrderWeek $from, CustomerOrderWeek $to): void
    {
        if ($from === null) {
            return;
        }

        foreach ($from->lines as $line) {
            $to->lines()->create($this->lineAttributes($line->position, $line->product_id, $line->quantities(), $line->container_type));
        }
    }
}
