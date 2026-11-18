<?php

namespace App\Http\Requests;

use App\Enums\ContainerType;
use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'yield_quantity' => ['required', 'numeric', 'min:0.001'],
            'yield_unit' => ['required', new Enum(Unit::class)],
            'is_semi_elaborate' => ['boolean'],
            // Opcional: lo que rinde se pide en bandejas o latas en las planillas de
            // pedidos, con cuánto entra en una (en la unidad de rendimiento).
            'container_type' => ['nullable', new Enum(ContainerType::class)],
            'units_per_container' => ['nullable', 'required_with:container_type', 'numeric', 'gt:0', 'max:99999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'container_type' => 'bandeja o lata',
            'units_per_container' => 'cantidad por bandeja o lata',
        ];
    }
}
