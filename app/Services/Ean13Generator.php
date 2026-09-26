<?php

namespace App\Services;

/**
 * Genera códigos EAN-13 internos ("de tienda") válidos para artículos sin código
 * de barras real. Usa el prefijo 2 (rango reservado para códigos internos por el
 * estándar EAN, así que no choca con barcodes de fábrica que empiezan 0-1, 3-9),
 * 11 dígitos de carga y el dígito verificador estándar. Son códigos de barra
 * reales: se pueden imprimir y escanear en el POS.
 */
class Ean13Generator
{
    /**
     * Devuelve un EAN-13 con el prefijo dado (1 dígito) y carga aleatoria.
     * El dígito 13 es el verificador.
     */
    public function generate(string $prefix = '2'): string
    {
        $payloadLength = 12 - strlen($prefix);
        $payload = '';
        for ($i = 0; $i < $payloadLength; $i++) {
            $payload .= random_int(0, 9);
        }

        $twelve = $prefix.$payload;

        return $twelve.$this->checkDigit($twelve);
    }

    /**
     * Dígito verificador EAN-13 de los primeros 12 dígitos: posiciones impares ×1,
     * pares ×3 (1-indexadas); verificador = (10 − (suma mod 10)) mod 10.
     */
    public function checkDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * (($index % 2 === 0) ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    /** Valida que un string sea un EAN-13 bien formado (13 dígitos + verificador correcto). */
    public function isValid(string $code): bool
    {
        if (! preg_match('/^\d{13}$/', $code)) {
            return false;
        }

        return $this->checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }
}
