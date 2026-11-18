<?php

namespace App\Http\Requests;

use App\Models\OrderSheetLine;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guardado completo de una semana de una planilla: la fecha de la semana y el
 * set entero de filas (artículo + siete cantidades, lunes a domingo).
 */
class SaveOrderSheetWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'lines' => ['present', 'array', 'max:500'],
            'lines.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'lines.*.quantities' => ['required', 'array', 'size:'.OrderSheetLine::DAYS],
            'lines.*.quantities.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.*.product_id.distinct' => 'Un artículo no puede repetirse en la misma planilla.',
            'lines.*.product_id.exists' => 'Alguno de los artículos ya no existe.',
            'lines.*.quantities.*.min' => 'Las cantidades no pueden ser negativas.',
            'lines.*.quantities.*.numeric' => 'Las cantidades tienen que ser números.',
        ];
    }
}
