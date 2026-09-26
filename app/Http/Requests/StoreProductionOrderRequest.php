<?php

namespace App\Http\Requests;

use App\Enums\ProductionOrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            // Instant queda afuera: sólo nace del flujo de orden instantánea,
            // nunca del alta manual (ver ProductionOrderType::selectable()).
            'type' => ['required', Rule::enum(ProductionOrderType::class)->except(ProductionOrderType::Instant)],
            'scheduled_for' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
