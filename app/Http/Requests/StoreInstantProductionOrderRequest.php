<?php

namespace App\Http\Requests;

use App\Enums\DeliveryDestinationType;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstantProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'destination_type' => ['required', Rule::enum(DeliveryDestinationType::class)],
            'destination_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($tenant) {
                $type = DeliveryDestinationType::tryFrom((string) $this->input('destination_type'));

                $exists = match ($type) {
                    DeliveryDestinationType::Location => $tenant->locations()->whereKey($value)->exists(),
                    DeliveryDestinationType::DeliveryPerson => $tenant->deliveryPeople()->whereKey($value)->exists(),
                    default => false,
                };

                if (! $exists) {
                    $fail('El destino elegido no es válido.');
                }
            }],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...static::itemRules($tenant),
        ];
    }

    /**
     * Reglas de los renglones (artículo + cantidad), compartidas con el
     * endpoint de preview — que valida lo mismo pero sin destino, porque el
     * costo de fabricar no depende de a dónde se despache.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function itemRules(Tenant $tenant): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::in($tenant->products()->producible()->pluck('id'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.in' => 'El artículo no está disponible para producir.',
        ];
    }
}
