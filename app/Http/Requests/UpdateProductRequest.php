<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'recipe_id' => [
                Rule::requiredIf(fn () => $this->input('type') === ProductType::Manufactured->value),
                'nullable',
                'integer',
                Rule::exists('recipes', 'id')->where('tenant_id', $tenantId),
            ],
            'unit' => ['required', Rule::enum(Unit::class)],
            'cost_per_unit' => [
                Rule::requiredIf(fn () => $this->input('type') === ProductType::Resale->value),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999',
            ],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')
                    ->where('tenant_id', $tenantId)
                    ->ignore($this->route('product')->id),
            ],
        ];
    }
}
