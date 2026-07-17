<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores scanned receipts on the private disk (`storage/app/private`),
 * normalising oversized photos on the way in.
 *
 * Privado y no público: un comprobante es un dato fiscal (CUIT, precios de
 * proveedores) y el symlink `/storage` lo dejaría accesible sin login a
 * cualquiera que conociera la URL. Se sirven por una ruta autenticada.
 *
 * A diferencia de los comprobantes de compras, acá no hay fallback al disco
 * público: gastos nace en el disco privado, así que no existen archivos
 * anteriores que relocalizar.
 */
class ReceiptStorer
{
    public function __construct(private readonly InvoiceImagePreparer $preparer) {}

    /**
     * @param  string  $folder  Tenant-scoped prefix, e.g. "variable-expenses/7".
     * @return string The stored path.
     */
    public function store(UploadedFile $file, string $folder): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        [$contents, $mime] = $this->preparer->prepare($contents, $file->getMimeType() ?? 'image/jpeg');

        $path = $this->pathFor($folder, $mime);
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    /**
     * Stores already-read bytes, returning the path alongside what was written
     * so the caller can hand the same bytes to the vision API without a re-read.
     *
     * @return array{0: string, 1: string, 2: string} Path, bytes and mime type.
     */
    public function storeContents(string $contents, string $mime, string $folder): array
    {
        [$contents, $mime] = $this->preparer->prepare($contents, $mime);

        $path = $this->pathFor($folder, $mime);
        Storage::disk('local')->put($path, $contents);

        return [$path, $contents, $mime];
    }

    public function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Only accept a path the tenant actually owns. The path travels back from
     * the browser in a hidden input, so it is attacker-controlled: without this
     * guard a crafted value would point a record at another tenant's file.
     */
    public function safePath(?string $path, string $prefix): ?string
    {
        if (blank($path) || ! str_starts_with($path, rtrim($prefix, '/').'/')) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    private function pathFor(string $folder, string $mime): string
    {
        $extension = $mime === 'application/pdf' ? 'pdf' : 'jpg';

        return rtrim($folder, '/').'/'.Str::uuid()->toString().'.'.$extension;
    }
}
