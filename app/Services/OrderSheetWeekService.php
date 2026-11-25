<?php

namespace App\Services;

use App\Models\OrderSheet;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Services\Concerns\BuildsWeeklyGrid;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Semanas de una planilla de pedidos. Una semana nueva (actual o futura) nace
 * como copia de la última semana cargada de esa planilla, y desde ahí se edita
 * sola: lo que se cambie no toca las demás. Las semanas pasadas que nunca se
 * cargaron se muestran vacías y recién existen cuando se guarda algo.
 */
class OrderSheetWeekService
{
    use BuildsWeeklyGrid;

    /**
     * La semana indicada solo si ya existe. A diferencia de weekFor() nunca
     * crea nada: es lo que usa imprimir.
     */
    public function existingWeek(OrderSheet $sheet, CarbonImmutable $date): ?OrderSheetWeek
    {
        return $this->find($sheet, $this->mondayOf($date));
    }

    /**
     * Lo que tendría la semana sin crear nada: la semana si existe; si no, y
     * es la actual o una futura, la última semana anterior cargada (la que
     * se copiaría al abrirla). Una semana pasada sin datos da null, igual que
     * weekFor(). Es solo lectura: lo usa la planilla de amasado, que lee la
     * demanda de mañana y no debe crear semanas como efecto secundario.
     */
    public function previewWeek(OrderSheet $sheet, CarbonImmutable $date): ?OrderSheetWeek
    {
        $monday = $this->mondayOf($date);

        $existing = $this->find($sheet, $monday);

        if ($existing !== null || $monday->lt($this->mondayOf(CarbonImmutable::now()))) {
            return $existing;
        }

        return $this->previousWeek($sheet, $monday)?->load('lines.product.recipe');
    }

    /**
     * La semana pedida, o null si no existe ni corresponde crearla (una
     * semana pasada sin datos). Si es la actual o una futura y no existe, se
     * crea copiando la última semana anterior que tenga la planilla.
     */
    public function weekFor(OrderSheet $sheet, CarbonImmutable $date): ?OrderSheetWeek
    {
        $monday = $this->mondayOf($date);

        $existing = $this->find($sheet, $monday);

        if ($existing !== null) {
            return $existing;
        }

        if ($monday->lt($this->mondayOf(CarbonImmutable::now()))) {
            return null;
        }

        return $this->createFromPrevious($sheet, $monday);
    }

    /**
     * Crea (si falta) la semana indicada y la devuelve lista para editar. A
     * diferencia de weekFor(), también crea semanas pasadas: es lo que usa
     * guardar. La nueva semana arranca vacía si no hay una anterior.
     */
    public function ensureWeek(OrderSheet $sheet, CarbonImmutable $date): OrderSheetWeek
    {
        $monday = $this->mondayOf($date);

        return $this->find($sheet, $monday) ?? $this->createFromPrevious($sheet, $monday);
    }

    /**
     * Reemplaza el contenido de una semana. Cada fila trae el artículo y sus
     * siete cantidades (lunes a domingo); el orden del arreglo es el orden de
     * la grilla. Cada fila se guarda en la medida actual de su artículo (la de
     * su receta): bandejas/latas si tiene rinde, unidades si no.
     *
     * @param  array<int, array{product_id: int, quantities: array<int, float|int|string|null>}>  $lines
     */
    public function save(OrderSheetWeek $week, array $lines): void
    {
        $containers = $this->containersFor($lines);

        DB::transaction(function () use ($week, $lines, $containers) {
            $week->lines()->delete();

            foreach (array_values($lines) as $position => $line) {
                $productId = (int) $line['product_id'];
                $week->lines()->create($this->lineAttributes($position, $productId, $line['quantities'], $containers[$productId] ?? null));
            }

            // Un artículo que entra a la lista de una planilla y no tiene destino
            // pasa a sumar ahí lo que pidan los clientes. Si ya apunta a otra, se respeta.
            Product::query()
                ->whereIn('id', array_map(fn (array $line): int => (int) $line['product_id'], $lines))
                ->whereNull('order_sheet_id')
                ->update(['order_sheet_id' => $week->order_sheet_id]);

            $week->touch();
        });
    }

    /**
     * Vuelve a copiar la última semana anterior sobre esta: descarta lo
     * cargado en la semana y deja lo de la anterior. Sin anterior la deja
     * vacía.
     */
    public function recopy(OrderSheetWeek $week): void
    {
        $week->loadMissing('orderSheet');

        DB::transaction(function () use ($week) {
            $week->lines()->delete();
            $this->copyLines($this->previousWeek($week->orderSheet, CarbonImmutable::parse($week->starts_on)), $week);
        });
    }

    private function find(OrderSheet $sheet, CarbonImmutable $monday): ?OrderSheetWeek
    {
        return $sheet->weeks()->whereDate('starts_on', $monday->toDateString())->with('lines.product.recipe')->first();
    }

    /**
     * La última semana anterior a la pedida que exista (la de fecha menor
     * más cercana; puede no ser la de la semana pasada si hubo huecos).
     */
    private function previousWeek(OrderSheet $sheet, CarbonImmutable $monday): ?OrderSheetWeek
    {
        return $sheet->weeks()
            ->whereDate('starts_on', '<', $monday->toDateString())
            ->orderByDesc('starts_on')
            ->with('lines')
            ->first();
    }

    private function createFromPrevious(OrderSheet $sheet, CarbonImmutable $monday): OrderSheetWeek
    {
        try {
            $week = DB::transaction(function () use ($sheet, $monday) {
                $week = $sheet->weeks()->create([
                    'tenant_id' => $sheet->tenant_id,
                    'starts_on' => $monday->toDateString(),
                ]);

                $this->copyLines($this->previousWeek($sheet, $monday), $week);

                return $week;
            });
        } catch (QueryException $exception) {
            // Doble alta simultánea: el unique (planilla, lunes) frenó a la segunda.
            $existing = $this->find($sheet, $monday);

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }

        return $this->find($sheet, $monday) ?? $week;
    }

    private function copyLines(?OrderSheetWeek $from, OrderSheetWeek $to): void
    {
        if ($from === null) {
            return;
        }

        foreach ($from->lines as $line) {
            $to->lines()->create($this->lineAttributes($line->position, $line->product_id, $line->quantities(), $line->container_type));
        }
    }
}
