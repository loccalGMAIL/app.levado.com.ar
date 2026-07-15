<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'unit' => ['required', Rule::enum(Unit::class)],
            'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'subdivisions' => ['nullable', 'integer', 'min:2'],
            'subdivision_label' => ['nullable', 'string', 'max:50'],
        ];
    }
}
