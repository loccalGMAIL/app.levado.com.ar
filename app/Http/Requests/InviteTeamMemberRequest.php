<?php

namespace App\Http\Requests;

use App\Enums\TenantUserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(TenantUserRole::class)->only([
                TenantUserRole::Owner,
                TenantUserRole::Admin,
                TenantUserRole::Viewer,
            ])],
        ];
    }
}
