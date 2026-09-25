<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * Encabezado de negocio compartido por los reportes imprimibles (gastos,
 * planillas de producción/reparto): nombre, razón social, CUIT, IVA y logo
 * en data URI, resuelto una sola vez para pantalla y PDF.
 */
class ReportLetterhead
{
    /**
     * @return array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string}
     */
    public function for(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'razon_social' => $tenant->razon_social,
            'cuit' => $tenant->cuit,
            'condicion_iva' => $tenant->condicion_iva?->label(),
            'currency' => $tenant->currency ?? 'ARS',
            'logo' => $this->logoDataUri($tenant),
        ];
    }

    /**
     * dompdf no acepta `Storage::url()` (necesita filesystem local o data
     * URI, no una URL relativa que dependa de APP_URL). Se resuelve acá una
     * sola vez y sirve para las dos salidas -pantalla y PDF- sin ramas por
     * formato en la vista.
     */
    private function logoDataUri(Tenant $tenant): ?string
    {
        if (! $tenant->logo_path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($tenant->logo_path) || $disk->size($tenant->logo_path) > 512 * 1024) {
            return null;
        }

        $mime = $disk->mimeType($tenant->logo_path);

        // dompdf no renderiza SVG ni WebP; sin un tipo soportado, cae al
        // fallback de nombre del negocio en vez de dejar un hueco roto.
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($tenant->logo_path));
    }
}
