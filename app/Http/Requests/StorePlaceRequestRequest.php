<?php

namespace App\Http\Requests;

use App\Enums\DeliveryDestinationType;
use App\Http\Requests\Concerns\ResolvesDestinationRef;
use App\Models\Tenant;
use App\Rules\ValidDestination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta suelta de un pedido (sin pasar por crear una orden): destino, fecha
 * de entrega y artículos. La orden del día la busca sola
 * ProductionOrderService::placeRequest().
 */
class StorePlaceRequestRequest extends FormRequest
{
    use ResolvesDestinationRef;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->resolveDestinationRef();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'destination_type' => ['required', Rule::enum(DeliveryDestinationType::class)],
            'destination_id' => ['required', 'integer', new ValidDestination($tenant, $this->input('destination_type'))],
            'scheduled_for' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'copy_previous' => ['nullable', 'boolean'],
            // present y no required: un pedido puede cargarse sin líneas
            // todavía (se completan después vía sync()), o directamente con
            // copy_previous marcado y sin líneas propias.
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.product_id' => ['required', 'integer', Rule::in($tenant->products()->producible()->pluck('id'))],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            // Recurrencia opcional: días de semana elegibles (ISO 1..7,
            // ver RecurringProductionRequest::occursOn()) + vigencia hasta
            // opcional. starts_on nunca se pide — nace de scheduled_for.
            'recurrence' => ['nullable', 'array'],
            'recurrence.weekdays' => ['required_with:recurrence', 'array', 'min:1'],
            'recurrence.weekdays.*' => ['integer', 'between:1,7'],
            'recurrence.ends_on' => ['nullable', 'date', 'after_or_equal:scheduled_for'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.product_id.in' => 'El artículo no está disponible para producir.',
        ];
    }
}
