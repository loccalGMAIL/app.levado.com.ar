<?php

namespace App\Http\Controllers\Concerns;

/**
 * Cantidad de filas por página de los listados. Se elige con ?per_page= y se
 * recuerda en la sesión por listado (clave por nombre de ruta), así que cada
 * pantalla conserva lo suyo sin pasar claves a mano.
 */
trait ResolvesPerPage
{
    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [20, 50, 100, 200];

    protected function perPage(int $default = 20): int
    {
        $key = 'per_page.'.(request()->route()?->getName() ?? request()->path());
        $requested = request()->integer('per_page');

        if (in_array($requested, self::PER_PAGE_OPTIONS, true)) {
            session([$key => $requested]);

            return $requested;
        }

        $remembered = (int) session($key, $default);

        return in_array($remembered, self::PER_PAGE_OPTIONS, true) ? $remembered : $default;
    }
}
