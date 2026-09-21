<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFixedCostPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `amounts` viene indexado por fixed_cost_id => monto. Que esas claves
     * pertenezcan al tenant actual no se valida acá -Laravel no valida claves
     * de array, solo valores- sino en el controller, filtrando contra los
     * gastos fijos propios antes de guardar.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'period' => 'mes',
        ];
    }
}
