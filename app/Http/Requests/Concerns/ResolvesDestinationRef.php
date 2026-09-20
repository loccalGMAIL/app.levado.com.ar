<?php

namespace App\Http\Requests\Concerns;

use App\Enums\DeliveryDestinationType;

/**
 * Traduce el select unificado de destino ("location:3" / "customer:7", ver
 * partials/destination-select.blade.php) al par destination_type/
 * destination_id que valida el resto del Form Request. Sólo actúa si viene
 * `destination` — un caller que ya manda el par por separado (tests,
 * previousLines() por querystring) sigue funcionando sin tocarlo.
 */
trait ResolvesDestinationRef
{
    protected function resolveDestinationRef(): void
    {
        if (! $this->filled('destination') || $this->filled('destination_type')) {
            return;
        }

        [$type, $id] = DeliveryDestinationType::splitRef($this->string('destination')->toString());

        $this->merge(['destination_type' => $type, 'destination_id' => $id]);
    }
}
