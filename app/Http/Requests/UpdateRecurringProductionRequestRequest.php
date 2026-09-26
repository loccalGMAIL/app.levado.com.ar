<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edita un molde recurrente ya existente: días, vigencia, notas y
 * artículos. El destino NO se edita acá — cambiarlo es, en la práctica,
 * pausar este y cargar un pedido nuevo con "Se repite" a otro destino.
 */
class UpdateRecurringProductionRequestRequest extends FormRequest
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
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'ends_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', Rule::in($tenant->products()->producible()->pluck('id'))],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ];
    }
}
