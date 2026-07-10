<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counted_quantity' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'counted_quantity.gte' => 'La cantidad contada no puede ser negativa.',
        ];
    }
}
