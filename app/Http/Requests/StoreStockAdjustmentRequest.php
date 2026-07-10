<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => 'La cantidad del ajuste no puede ser cero.',
            'reason.required' => 'El motivo es obligatorio para los ajustes.',
        ];
    }
}
