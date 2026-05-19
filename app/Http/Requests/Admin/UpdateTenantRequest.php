<?php

namespace App\Http\Requests\Admin;

use App\Enums\CondicionIva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'string', 'max:20', 'regex:/^\d{2}-?\d{8}-?\d{1}$/'],
            'condicion_iva' => ['nullable', Rule::enum(CondicionIva::class)],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'productive_hours_month' => ['required', 'integer', 'min:1', 'max:744'],
        ];
    }
}
