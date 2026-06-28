<?php

namespace App\Services;

/**
 * Downscales an oversized invoice photo before it is base64-encoded and sent to
 * the vision API. The client already shrinks camera photos, so this is defence
 * in depth for uploads that bypass the JS (disabled scripts, desktop) and keeps
 * PHP memory, the API payload and storage small.
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

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return [$contents, $mime];
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            if (max($width, $height) <= self::MAX_EDGE) {
                return [$contents, $mime];
            }

            $scaled = imagescale($image, self::scaledWidth($width, $height), -1, IMG_BILINEAR_FIXED);
            if ($scaled === false) {
                return [$contents, $mime];
            }

            try {
                ob_start();
                $ok = imagejpeg($scaled, null, self::JPEG_QUALITY);
                $buffer = (string) ob_get_clean();

                return $ok ? [$buffer, 'image/jpeg'] : [$contents, $mime];
            } finally {
                imagedestroy($scaled);
            }
        } finally {
            imagedestroy($image);
        }
    }

    private static function scaledWidth(int $width, int $height): int
    {
        if ($width >= $height) {
            return self::MAX_EDGE;
        }

        return max(1, (int) round($width * (self::MAX_EDGE / $height)));
    }
}
