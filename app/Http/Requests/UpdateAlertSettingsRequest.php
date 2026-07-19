<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya está acotada a super_admin/owner
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'cost_spike_threshold_pct' => ['required', 'numeric', 'min:1', 'max:1000'],
            'stale_cost_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cost_spike_threshold_pct' => 'porcentaje de salto de costo',
            'stale_cost_days' => 'días para costo desactualizado',
        ];
    }
}
