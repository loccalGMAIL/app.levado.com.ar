<?php

namespace App\Enums;

use App\Models\DeliveryPerson;
use App\Models\Location;

/**
 * Dueño único del discriminador polimórfico que viaja en
 * production_order_requests.destination_type (y es clave del morph map real
 * de Eloquent, fusionado con CatalogItemType en AppServiceProvider). El
 * destino de un pedido de producción: hoy sucursal o repartidor.
 *
 * Pensado para crecer: el repartidor va a tener clientes propios (fuera de
 * alcance de esta fase) — sumar `Customer` es un case más acá y una entrada
 * más en el morph map, sin re-modelar production_order_requests.
 */
enum DeliveryDestinationType: string
{
    case Location = 'location';
    case DeliveryPerson = 'delivery_person';

    public function label(): string
    {
        return match ($this) {
            self::Location => 'Sucursal',
            self::DeliveryPerson => 'Repartidor',
        };
    }

    /** @return class-string<Location|DeliveryPerson> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Location => Location::class,
            self::DeliveryPerson => DeliveryPerson::class,
        };
    }

    public static function for(Location|DeliveryPerson $destination): self
    {
        return match (true) {
            $destination instanceof Location => self::Location,
            $destination instanceof DeliveryPerson => self::DeliveryPerson,
        };
    }

    /**
     * Si el destino tiene stock propio (sucursal) o no (repartidor). Sin uso
     * hoy — el destino de esta fase es informativo — pero es el gancho donde
     * la fase de transferencias distinguirá "mover stock" de "sólo despachar".
     */
    public function holdsStock(): bool
    {
        return $this === self::Location;
    }
}
