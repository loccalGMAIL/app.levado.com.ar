<?php

namespace App\Http\Requests\Admin;

use App\Enums\TenantUserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'role' => ['required', Rule::enum(TenantUserRole::class)],
        ];
    }
}
