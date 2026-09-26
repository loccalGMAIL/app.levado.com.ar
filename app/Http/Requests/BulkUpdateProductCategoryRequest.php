<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateProductCategoryRequest extends FormRequest
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
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer'],
            // null = "quitar categoría" (el <option value=""> vacío ya llega
            // como null por ConvertEmptyStringsToNull).
            'product_category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
