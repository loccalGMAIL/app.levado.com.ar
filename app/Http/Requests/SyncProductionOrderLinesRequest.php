<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProductionOrderLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            // present y no required: guardar la grilla vacía es "borrá todas
            // las líneas del pedido", una operación legítima.
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['required', 'integer', Rule::in($tenant->products()->producible()->pluck('id'))],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.product_id.in' => 'El artículo no está disponible para producir.',
        ];
    }
}
