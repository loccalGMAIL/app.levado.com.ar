<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * La unidad de compra no se puede convertir a la del ítem y no hay divisor
 * (ni explícito, ni recordado, ni legible de la descripción). Tipada para que
 * quien aplica en lote la distinga de un fallo inesperado sin leer el mensaje.
 */
class IncompatibleUnitsException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }
}
