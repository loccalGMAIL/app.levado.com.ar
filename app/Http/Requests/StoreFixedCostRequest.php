<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'fixed_cost_category_id' => [
                'required',
                'integer',
                Rule::exists('fixed_cost_categories', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'monthly_amount' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
        ];
    }
}
