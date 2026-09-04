<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryPersonRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('delivery_people', 'name')->where('tenant_id', $tenant->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
