<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unidad de producción de una receta: su nombre (lata, bandeja...) y a
 * cuánto rinde equivale una (en la unidad del rinde). Ambos vacíos vuelven
 * al default: la unidad es el rinde completo y se llama «Lata».
 */
class UpdateRecipeProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'production_unit_name' => ['nullable', 'string', 'max:30', 'required_with:production_unit_quantity'],
            'production_unit_quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999', 'required_with:production_unit_name'],
        ];
    }
}
