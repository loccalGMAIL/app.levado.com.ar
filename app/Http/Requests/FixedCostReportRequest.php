<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class FixedCostReportRequest extends FormRequest
{
    /** Secciones válidas del reporte, en el orden en que se imprimen. */
    public const SECTIONS = ['current', 'monthly', 'details', 'variable'];

    /** Tope duro del rango: acota memoria/tiempo del PDF y el tamaño de `details`. */
    private const MAX_MONTHS = 24;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sections' => ['nullable', 'array', 'max:'.count(self::SECTIONS)],
            'sections.*' => ['in:'.implode(',', self::SECTIONS)],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'category' => ['nullable', 'integer'],
            've_search' => ['nullable', 'string', 'max:100'],
            've_category' => ['nullable', 'array'],
            've_category.*' => ['integer'],
            've_supplier' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'from' => 'fecha desde',
            'to' => 'fecha hasta',
            'category' => 'categoría',
            've_search' => 'búsqueda de gastos variables',
            've_category' => 'categoría de gastos variables',
            've_supplier' => 'proveedor',
        ];
    }

    /**
     * Validación cruzada de `from`/`to`: no va como regla `after_or_equal:from`
     * para controlar el mensaje en español a mano y compartir el mismo chequeo
     * con el tope de 24 meses (las reglas de comparación de fecha de Laravel no
     * dan para las dos cosas en una).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! $from || ! $to) {
                return;
            }

            $from = Carbon::parse($from)->startOfDay();
            $to = Carbon::parse($to)->startOfDay();

            if ($to->lt($from)) {
                $validator->errors()->add('to', 'La fecha «hasta» no puede ser anterior a «desde».');

                return;
            }

            $months = $from->copy()->startOfMonth()->diffInMonths($to->copy()->startOfMonth()) + 1;
            if ($months > self::MAX_MONTHS) {
                $validator->errors()->add('to', 'El rango no puede superar los 24 meses.');
            }
        });
    }

    /**
     * Normaliza los parámetros ya validados a la forma que consume
     * `FixedCostReport::build()`, para que controlador y service no repitan
     * el parseo de fechas ni los defaults.
     *
     * Default sin `to`: hoy. Default sin `from`: 12 meses atrás desde `to`
     * (mismo alcance que tenía antes `months=12`, ahora anclado a fechas).
     *
     * @return array{from: Carbon, to: Carbon, sections: list<string>, search: ?string, status: ?string, category: ?int, ve_search: ?string, ve_category: list<int>, ve_supplier: ?int}
     */
    public function options(): array
    {
        $to = $this->validated('to') ? Carbon::parse($this->validated('to')) : Carbon::now();
        $from = $this->validated('from') ? Carbon::parse($this->validated('from')) : $to->copy()->subMonths(11)->startOfMonth();

        $sections = $this->validated('sections') ?: ['current', 'monthly'];

        return [
            'from' => $from->startOfDay(),
            'to' => $to->startOfDay(),
            'sections' => array_values(array_intersect(self::SECTIONS, $sections)),
            'search' => $this->validated('search'),
            'status' => $this->validated('status'),
            'category' => $this->validated('category') ? (int) $this->validated('category') : null,
            've_search' => $this->validated('ve_search'),
            've_category' => array_map('intval', $this->validated('ve_category') ?: []),
            've_supplier' => $this->validated('ve_supplier') ? (int) $this->validated('ve_supplier') : null,
        ];
    }
}
