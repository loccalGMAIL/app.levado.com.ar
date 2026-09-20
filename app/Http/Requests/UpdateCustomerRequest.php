<?php

namespace App\Http\Requests;

use App\Enums\CondicionIva;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenant = app(Tenant::class);
        $customer = $this->route('customer');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customers', 'name')
                    ->where('tenant_id', $tenant->id)
                    ->ignore($customer),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_person_id' => [
                'nullable', 'integer',
                Rule::exists('delivery_people', 'id')->where('tenant_id', $tenant->id),
            ],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'condicion_iva' => ['nullable', Rule::enum(CondicionIva::class)],
        ];
    }
}
