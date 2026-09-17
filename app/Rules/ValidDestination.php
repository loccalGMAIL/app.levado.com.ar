<?php

namespace App\Rules;

use App\Enums\DeliveryDestinationType;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El destino (sucursal o repartidor) existe y es de este negocio. Extraída
 * de StoreInstantProductionOrderRequest — se comparte con
 * ProductionOrderRequestController::store() y con el alta suelta de un
 * pedido (ProductionRequestController::store()), que antes repetían el
 * mismo match+exists() cada una por su lado.
 */
class ValidDestination implements ValidationRule
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly ?string $destinationType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = DeliveryDestinationType::tryFrom((string) $this->destinationType);

        $exists = match ($type) {
            DeliveryDestinationType::Location => $this->tenant->locations()->whereKey($value)->exists(),
            DeliveryDestinationType::DeliveryPerson => $this->tenant->deliveryPeople()->whereKey($value)->exists(),
            default => false,
        };

        if (! $exists) {
            $fail('El destino elegido no es válido.');
        }
    }
}
