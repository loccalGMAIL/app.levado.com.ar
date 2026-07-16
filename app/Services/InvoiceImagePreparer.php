<?php

namespace App\Services;

use GdImage;

/**
 * Normalises an invoice photo before it is base64-encoded and sent to the
 * vision API: la endereza según su EXIF y la achica si es enorme.
 *
 * Phone cameras produce 12–48 MP photos. Rendering one full-resolution in an
 * <img> preview (or uploading it as-is) can exhaust memory on low-end devices,
 * surfacing "Memoria insuficiente para completar la operación anterior". We
 * shrink the photo to a sane size and re-encode it as JPEG before it is ever
 * previewed or uploaded.
 *
 * The client already shrinks and straightens camera photos, so this is defence
 * in depth for uploads that bypass the JS (disabled scripts, desktop, or the
 * client-side compressor bailing out and sending the original).
 */
class InvoiceImagePreparer
{
    private const MAX_EDGE = 1600;

    private const JPEG_QUALITY = 80;

    /**
     * @return array{0: string, 1: string} The (possibly re-encoded) bytes and mime type.
     */
    public function prepare(string $contents, string $mime): array
    {
        if ($mime === 'application/pdf' || ! extension_loaded('gd')) {
            return [$contents, $mime];
        }

        $orientation = $this->orientationOf($contents);
        $mustOrient = $orientation !== null && $orientation !== 1;

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return [$contents, $mime];
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $mustScale = max($width, $height) > self::MAX_EDGE;

            if (! $mustScale && ! $mustOrient) {
                return [$contents, $mime];
            }

            $prepared = $image;
            $derived = [];

            // Achicar primero: rotar la imagen chica cuesta bastante menos.
            if ($mustScale) {
                $scaled = imagescale($prepared, self::scaledWidth($width, $height), -1, IMG_BILINEAR_FIXED);
                if ($scaled === false) {
                    return [$contents, $mime];
                }
                $prepared = $scaled;
                $derived[] = $scaled;
            }

            try {
                if ($mustOrient) {
                    $oriented = $this->applyOrientation($prepared, $orientation);
                    if ($oriented === false) {
                        return [$contents, $mime];
                    }
                    $prepared = $oriented;
                    $derived[] = $oriented;
                }

                ob_start();
                $ok = imagejpeg($prepared, null, self::JPEG_QUALITY);
                $buffer = (string) ob_get_clean();

                return $ok ? [$buffer, 'image/jpeg'] : [$contents, $mime];
            } finally {
                foreach ($derived as $resource) {
                    imagedestroy($resource);
                }
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * El teléfono no rota los píxeles: los deja como salieron del sensor y
     * anota en el EXIF cómo hay que mostrarlos. Como imagejpeg() descarta el
     * EXIF, hay que hornear ese giro en los píxeles ANTES de re-encodear; si
     * no, la foto queda acostada para siempre y ya no hay dato que lo diga.
     */
    private function applyOrientation(GdImage $image, int $orientation): GdImage|false
    {
        // imagerotate() gira en sentido antihorario; el EXIF se define en horario.
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        $mirrored = in_array($orientation, [2, 4, 5, 7], true);

        // Con 0° imagerotate() igual devuelve una imagen nueva, así que sirve
        // de copia para los casos que sólo espejan.
        $result = imagerotate($image, $angle, imagecolorallocate($image, 255, 255, 255));
        if ($result === false) {
            return false;
        }

        if ($mirrored && ! imageflip($result, IMG_FLIP_HORIZONTAL)) {
            imagedestroy($result);

            return false;
        }

        return $result;
    }

    private function orientationOf(string $contents): ?int
    {
        if (! extension_loaded('exif')) {
            return null;
        }

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return null;
        }

        try {
            fwrite($stream, $contents);
            rewind($stream);
            $exif = @exif_read_data($stream);
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($stream);
        }

        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        return is_int($orientation) && $orientation >= 1 && $orientation <= 8
            ? $orientation
            : null;
    }

    private static function scaledWidth(int $width, int $height): int
    {
        if ($width >= $height) {
            return self::MAX_EDGE;
        }

        return max(1, (int) round($width * (self::MAX_EDGE / $height)));
    }
}
