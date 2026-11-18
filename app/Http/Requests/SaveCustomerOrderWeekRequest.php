<?php

namespace App\Http\Requests;

/**
 * Guardado completo de una semana de pedidos de un cliente: mismo formato que
 * el de una planilla (fecha de la semana + set entero de filas).
 */
class SaveCustomerOrderWeekRequest extends SaveOrderSheetWeekRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'lines.*.product_id.distinct' => 'Un artículo no puede repetirse en el pedido de un cliente.',
        ];
    }
}
