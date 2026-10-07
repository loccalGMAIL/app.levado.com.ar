<?php

namespace App\Http\Requests;

use App\Enums\MobileShortcut;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileShortcutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya está acotada a super_admin/owner
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'shortcuts' => ['required', 'array', 'size:'.MobileShortcut::SLOTS],
            'shortcuts.*' => ['required', 'distinct', Rule::enum(MobileShortcut::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'shortcuts' => 'accesos rápidos',
            'shortcuts.*' => 'acceso rápido',
        ];
    }
}
