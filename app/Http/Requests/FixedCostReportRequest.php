<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class FixedCostReportRequest extends FormRequest
{
    /** Secciones válidas del reporte, en el orden en que se imprimen. */
    public const SECTIONS = ['current', 'monthly', 'details', 'variable'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'date_format:Y-m'],
            'months' => ['nullable', 'integer', 'min:3', 'max:24'],
            'sections' => ['nullable', 'array', 'max:'.count(self::SECTIONS)],
            'sections.*' => ['in:'.implode(',', self::SECTIONS)],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'period' => 'mes',
            'months' => 'cantidad de meses',
        ];
    }

    /**
     * Normaliza los parámetros ya validados a la forma que consume
     * `FixedCostReport::build()`, para que controlador y service no repitan
     * el parseo de `period` ni los defaults.
     *
     * @return array{period: Carbon, months: int, sections: list<string>, search: ?string, status: ?string}
     */
    public function options(): array
    {
        $period = $this->validated('period')
            ? Carbon::createFromFormat('Y-m', $this->validated('period'))->startOfMonth()
            : Carbon::now()->startOfMonth();

        $sections = $this->validated('sections') ?: ['current', 'monthly'];

        return [
            'period' => $period,
            'months' => (int) ($this->validated('months') ?: 12),
            'sections' => array_values(array_intersect(self::SECTIONS, $sections)),
            'search' => $this->validated('search'),
            'status' => $this->validated('status'),
        ];
    }
}
