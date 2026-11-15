<?php

namespace App\Http\Requests;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // Unidades de la receta que contiene 1 unidad del artículo (1 = artículo base, >1 = pack).
            'recipe_quantity' => ['nullable', 'numeric', 'min:1', 'max:99999'],
            'product_category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'unit' => ['required', Rule::enum(Unit::class)],
            'cost_per_unit' => [
                Rule::requiredIf(fn () => $this->input('type') === ProductType::Resale->value),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999',
            ],
            'costing_method' => ['nullable', Rule::enum(CostingMethod::class)],
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

    /**
     * Una receta tiene un único artículo base (el que se produce y da el precio
     * de la receta); los demás artículos de la misma receta son presentaciones.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $isBase = $this->input('type') === ProductType::Manufactured->value
                && $this->filled('recipe_id')
                && (! $this->filled('recipe_quantity') || (float) $this->input('recipe_quantity') === 1.0);

            if (! $isBase || $validator->errors()->isNotEmpty()) {
                return;
            }

            $existing = Product::baseArticleNameFor(app(Tenant::class)->id, (int) $this->input('recipe_id'), $this->route('product')->id);

            if ($existing !== null) {
                $validator->errors()->add(
                    'recipe_id',
                    "Esa receta ya tiene su artículo base ({$existing}). Para vender otra presentación indicá una cantidad mayor a 1.",
                );
            }
        }];
    }
}
