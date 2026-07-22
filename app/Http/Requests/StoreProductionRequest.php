<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenantId = app(Tenant::class)->id;

        return [
            'product_id' => [
                'required',
                'integer',
                // Solo un elaborado del tenant se puede producir; el service revalida receta activa y unidad.
                Rule::exists('products', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('type', ProductType::Manufactured->value),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
