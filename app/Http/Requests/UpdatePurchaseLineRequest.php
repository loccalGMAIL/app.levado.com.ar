<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raw_name' => ['nullable', 'string', 'max:255'],
            'quantity_purchased' => ['required', 'numeric', 'min:0.0001'],
            'purchase_unit' => ['required', Rule::enum(Unit::class)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'iva_rate' => ['nullable', 'numeric', 'in:0,0.105,0.21'],
            'percepcion_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_bonus' => ['nullable', 'boolean'],
        ];
    }
}
