<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Digitises a purchase invoice image/PDF with Claude vision.
 *
 * Phase 1 is pure transcription: the model copies the invoice as printed
 * (quantities, prices) WITHOUT any arithmetic — no pack math, no VAT, no unit
 * conversion. Matching against the catalog and cost calculation happen later
 * (phase 2). The result is always a draft for human review, never persisted.
 */
class InvoiceExtractor
{
    /**
     * @param  Collection<int, Ingredient>  $ingredients
     * @param  Collection<int, Packaging>  $packagings
     * @return array{
     *     header: array{supplier_name: ?string, cuit: ?string, invoice_number: ?string, invoice_date: ?string, total: ?float},
     *     lines: array<int, array{raw_name: ?string, quantity: ?float, unit: ?string, unit_price: ?float, matched_type: ?string, matched_id: ?int}>
     * }
     */
    public function extract(string $base64, string $mimeType, Collection $ingredients, Collection $packagings): array
    {
        $apiKey = config('services.anthropic.key');

        if (blank($apiKey)) {
            throw new RuntimeException('Falta configurar la API key de Anthropic (ANTHROPIC_API_KEY).');
        }

        $sourceBlock = $mimeType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]];

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
            'max_tokens' => 4096,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    $sourceBlock,
                    ['type' => 'text', 'text' => $this->buildPrompt($ingredients, $packagings)],
                ],
            ]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('La API de Anthropic respondió con un error ('.$response->status().'). Probá de nuevo en un momento.');
        }

        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? null;

        if ($text === null) {
            throw new RuntimeException('No se pudo leer la respuesta de la IA. Probá con una foto más nítida.');
        }

        return $this->normalize($this->decodeJson($text), $ingredients, $packagings);
    }

    /**
     * @param  Collection<int, Ingredient>  $ingredients
     * @param  Collection<int, Packaging>  $packagings
     */
    private function buildPrompt(Collection $ingredients, Collection $packagings): string
    {
        $ingredientCatalog = $ingredients
            ->map(fn (Ingredient $i) => "- id={$i->id} | {$i->name}".($i->brand ? " | marca: {$i->brand}" : ''))
            ->implode("\n");

        $packagingCatalog = $packagings
            ->map(fn (Packaging $p) => "- id={$p->id} | {$p->name}".($p->brand ? " | marca: {$p->brand}" : ''))
            ->implode("\n");

        $units = collect(Unit::cases())->map(fn (Unit $u) => $u->value)->implode(', ');

        return <<<PROMPT
            Sos un asistente que DIGITALIZA (transcribe) facturas de compra de una panadería argentina.
            Tu única tarea es COPIAR los datos tal como figuran en la factura.
            NO hagas NINGÚN cálculo: no multipliques, no dividas, no sumes IVA, no conviertas unidades.
            Solo reconocé y transcribí lo que ves.

            CATÁLOGO DE INSUMOS (ingredientes) del cliente:
            {$ingredientCatalog}

            CATÁLOGO DE DESCARTABLES (envases) del cliente:
            {$packagingCatalog}

            Por cada renglón de la factura devolvé:
            - raw_name: la descripción COMPLETA tal como figura (incluí el tamaño del envase si aparece, ej. "HARINA 3/0 X 25 Kg").
            - quantity: el número de la columna Cantidad, EXACTAMENTE como figura (la cantidad de bultos/unidades, o el peso si se vende suelto). NO lo multipliques por el tamaño del envase.
            - unit: la unidad de esa cantidad. Usá 'u' si se vende por bulto/bolsa/caja/bidón/unidad. Usá kg/gr/L/ml/cc si la cantidad ya es un peso o volumen. Opciones válidas: {$units}.
            - unit_price: el precio POR UNIDAD/bulto, tomado de la columna de PRECIO UNITARIO (suele llamarse "Unitario", "P.UNIT" o "PRECIO").
              ¡MUY IMPORTANTE! NO uses la columna "Total" ni "Subtotal" del renglón: esa es cantidad × precio, NO el precio unitario.
              Si la cantidad es mayor a 1, el unit_price tiene que ser MENOR que el total del renglón.
              Control obligatorio: unit_price × quantity debe dar aproximadamente el total de ese renglón. Si no da, estás tomando la columna equivocada.
              NO le sumes IVA, NO lo dividas, NO lo multipliques.
            - matched_type / matched_id: sugerencia del ítem más parecido del catálogo (por nombre/marca). null si no estás razonablemente seguro. (Esto es solo una sugerencia para después; no afecta los números.)

            NÚMEROS — esto es lo más importante, copiá el VALOR exacto:
            - Formato argentino: el punto separa miles y la coma los decimales. "13.891,40" = 13891.40 ; "2.778.280,00" = 2778280.00 ; "146,85" = 146.85.
            - Formato con coma de miles: "1,148,785.38" = 1148785.38 ; "7,822.85" = 7822.85.
            - Fijate en la columna y en los totales para no confundir el separador. Devolvé siempre el número con punto decimal.

            invoice_number: combiná Punto de Venta + N° de comprobante con guion (ej. "0012-00017065").
            ¡OJO! Transcribí los 4 dígitos del Punto de Venta uno por uno, tal como se ven. NO asumas "0001"
            ni ningún otro valor típico cuando la letra no sea perfectamente nítida.
            Este error ya pasó con facturas reales: "0006-00008298" fue leído como "0001-00008298".
            Si no podés leer con certeza alguno de los dígitos, devolvé invoice_number como null en vez de adivinar.

            invoice_date: devolvela en formato YYYY-MM-DD.
            ¡OJO! En Argentina las fechas se escriben DÍA/MES/AÑO. "02/06/2026" es el 2 de JUNIO de 2026 → "2026-06-02".
            NUNCA interpretes el primer número como si fuera el mes (eso sería formato de EE.UU.).
            Este error ya pasó con facturas reales: "02/06/2026" leída como 6 de febrero y "01/07/2026" como 7 de enero.

            total: el TOTAL final de la factura, tal como figura. Copiá todos sus dígitos: un cero de menos
            convierte 38.000 en 3.800. Control: el total tiene que ser coherente con la suma de los renglones.

            Ejemplos de transcripción fiel (sin cálculos):
            - "HARINA 3/0 X 25 Kg" · Cantidad 200 · Unitario 13.891,40
              → {"raw_name":"HARINA 3/0 X 25 Kg","quantity":200,"unit":"u","unit_price":13891.40,...}
            - "QUESO TYBO BARRA X kg" · Cantidad 146,85 Kg · Unitario 7.822,85
              → {"raw_name":"QUESO TYBO BARRA X kg","quantity":146.85,"unit":"kg","unit_price":7822.85,...}

            Devolvé ÚNICAMENTE este objeto JSON, sin texto adicional ni markdown:
            {
              "supplier_name": string|null,
              "cuit": string|null,
              "invoice_number": string|null,
              "invoice_date": "YYYY-MM-DD"|null,
              "total": number|null,
              "lines": [
                {
                  "raw_name": string,
                  "quantity": number|null,
                  "unit": string|null,
                  "unit_price": number|null,
                  "matched_type": "ingredient"|"packaging"|null,
                  "matched_id": number|null
                }
              ]
            }
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        // Strip markdown fences if the model wrapped the JSON.
        $clean = preg_replace('/^```(?:json)?|```$/m', '', trim($text)) ?? $text;

        // Keep only the outermost JSON object.
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('La IA no devolvió un JSON válido. Probá con una foto más nítida.');
        }

        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('No se pudieron interpretar los datos de la factura. Probá con una foto más nítida.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Ingredient>  $ingredients
     * @param  Collection<int, Packaging>  $packagings
     */
    private function normalize(array $data, Collection $ingredients, Collection $packagings): array
    {
        $ingredientIds = $ingredients->pluck('id')->all();
        $packagingIds = $packagings->pluck('id')->all();

        $lines = collect($data['lines'] ?? [])
            ->filter(fn ($line) => is_array($line))
            ->map(function (array $line) use ($ingredientIds, $packagingIds) {
                $type = CatalogItemType::tryFrom((string) ($line['matched_type'] ?? ''))?->value;
                $id = is_numeric($line['matched_id'] ?? null) ? (int) $line['matched_id'] : null;

                // Drop the suggestion if it doesn't belong to the tenant's catalog.
                $valid = ($type === CatalogItemType::Ingredient->value && in_array($id, $ingredientIds, true))
                    || ($type === CatalogItemType::Packaging->value && in_array($id, $packagingIds, true));

                if (! $valid) {
                    $type = null;
                    $id = null;
                }

                return [
                    'raw_name' => isset($line['raw_name']) ? (string) $line['raw_name'] : null,
                    'quantity' => $this->toFloat($line['quantity'] ?? null),
                    'unit' => $this->normalizeUnit($line['unit'] ?? null) ?? Unit::Unidad->value,
                    'unit_price' => $this->toFloat($line['unit_price'] ?? null),
                    'matched_type' => $type,
                    'matched_id' => $id,
                ];
            })
            ->values()
            ->all();

        return [
            'header' => [
                'supplier_name' => isset($data['supplier_name']) ? (string) $data['supplier_name'] : null,
                'cuit' => isset($data['cuit']) ? (string) $data['cuit'] : null,
                'invoice_number' => isset($data['invoice_number']) ? (string) $data['invoice_number'] : null,
                'invoice_date' => $this->normalizeDate($data['invoice_date'] ?? null),
                'total' => $this->toFloat($data['total'] ?? null),
            ],
            'lines' => $lines,
        ];
    }

    private function normalizeUnit(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            'gr', 'g', 'gramo', 'gramos' => Unit::Gramo->value,
            'kg', 'kgs', 'kilo', 'kilos', 'kilogramo', 'kilogramos' => Unit::Kilogramo->value,
            'ml', 'mililitro', 'mililitros' => Unit::Mililitro->value,
            'l', 'lt', 'lts', 'litro', 'litros' => Unit::Litro->value,
            'cc', 'cm3' => Unit::Centimetro3->value,
            'u', 'un', 'uni', 'unidad', 'unidades',
            'bulto', 'bultos', 'caja', 'cajas', 'bolsa', 'bolsas',
            'bidon', 'bidón', 'pack', 'pote', 'potes', 'lata', 'latas' => Unit::Unidad->value,
            default => Unit::tryFrom($value)?->value,
        };
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Fallback for stray Argentine-formatted strings ("1.234,56").
        if (is_string($value)) {
            $clean = str_replace(['.', ' '], '', $value);
            $clean = str_replace(',', '.', $clean);

            return is_numeric($clean) ? (float) $clean : null;
        }

        return null;
    }
}
