<?php

namespace App\Enums;

use App\Models\Customer;
use App\Models\Location;

/**
 * Dueño único del discriminador polimórfico que viaja en
 * production_order_requests.destination_type (y es clave del morph map real
 * de Eloquent, fusionado con CatalogItemType en AppServiceProvider). El
 * destino de un pedido de producción: sucursal o cliente — el repartidor
 * (`DeliveryPerson`) es quien LLEVA la mercadería, no quien la recibe, y
 * dejó de ser un destino (era una mala expresión del modelo). Un cliente
 * puede tener un repartidor a cargo (`Customer::deliveryPerson()`), gancho
 * para cuando un repartidor con login filtre "mis clientes".
 */
enum DeliveryDestinationType: string
{
    case Location = 'location';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Location => 'Sucursal',
            self::Customer => 'Cliente',
        };
    }

    /** @return class-string<Location|Customer> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Location => Location::class,
            self::Customer => Customer::class,
        };
    }

    public static function for(Location|Customer $destination): self
    {
        return match (true) {
            $destination instanceof Location => self::Location,
            $destination instanceof Customer => self::Customer,
        };
    }

    /**
     * Si el destino tiene stock propio (sucursal) o no (cliente). Sin uso
     * hoy — el destino de esta fase es informativo — pero es el gancho donde
     * la fase de transferencias distinguirá "mover stock" de "sólo despachar".
     */
    public function holdsStock(): bool
    {
        return $this === self::Location;
    }

    /**
     * Parte una referencia "tipo:id" del select unificado de destino (ver
     * partials/destination-select.blade.php) en el par que espera el resto
     * del dominio. Dueño único del split — lo usan los Form Requests vía
     * ResolvesDestinationRef, no lo repitas inline.
     *
     * @return array{0: ?string, 1: ?int}
     */
    public static function splitRef(?string $ref): array
    {
        if ($ref === null || ! str_contains($ref, ':')) {
            return [null, null];
        }

        [$type, $id] = explode(':', $ref, 2);

        return [$type, ctype_digit($id) ? (int) $id : null];
    }
}
