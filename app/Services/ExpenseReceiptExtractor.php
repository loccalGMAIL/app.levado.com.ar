<?php

namespace App\Services;

use App\Models\VariableExpenseCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a variable-expense receipt with Claude vision.
 *
 * Unlike InvoiceExtractor, this one has no line items: an expense is a single
 * amount, so a receipt listing several things gets summarised into one
 * description. The result is always a draft the user reviews in the modal.
 */
class ExpenseReceiptExtractor
{
    /**
     * @param  Collection<int, VariableExpenseCategory>  $categories
     * @return array{name: ?string, supplier_name: ?string, expense_date: ?string, amount: ?float, category_id: ?int}
     */
    public function extract(string $base64, string $mimeType, Collection $categories): array
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
            'max_tokens' => 1024,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    $sourceBlock,
                    ['type' => 'text', 'text' => $this->buildPrompt($categories)],
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

        return $this->normalize($this->decodeJson($text), $categories);
    }

    /**
     * @param  Collection<int, VariableExpenseCategory>  $categories
     */
    private function buildPrompt(Collection $categories): string
    {
        $catalog = $categories
            ->map(fn (VariableExpenseCategory $c) => "- id={$c->id} | {$c->name}")
            ->implode("\n");

        $today = Carbon::today()->toDateString();

        return <<<PROMPT
            Sos un asistente que DIGITALIZA comprobantes de gastos de una panadería argentina.
            Un gasto es un pago puntual: un flete, una reparación, una boleta de luz, una compra de ferretería.
            Tu única tarea es COPIAR lo que figura en el comprobante. NO hagas cálculos: no sumes, no restes, no saques IVA.

            CATEGORÍAS DE GASTO del cliente:
            {$catalog}

            Devolvé:
            - name: una descripción CORTA (máximo 60 caracteres) de qué se pagó.
              Si el comprobante tiene UN SOLO concepto, usá ese concepto (ej. "Cambio de cubierta trasera").
              Si tiene VARIOS ÍTEMS, resumilos en una sola frase, del rubro general a los ítems principales
              (ej. "Ferretería: tornillos, cinta y silicona"). NO devuelvas una lista ni un renglón por ítem.
              Si no se entiende qué se pagó, devolvé null. NO inventes.
            - amount: el TOTAL FINAL del comprobante, tal como figura. Es el importe que se pagó, con IVA incluido si lo tiene.
              Si hay varios totales (subtotal, IVA, total), tomá SIEMPRE el total final. NO lo recalcules.
            - expense_date: la fecha del comprobante en formato YYYY-MM-DD. Hoy es {$today}, usala para desambiguar años de 2 dígitos.
            - supplier_name: la razón social o nombre de fantasía de QUIEN EMITE el comprobante (el que cobra), no el cliente.
            - category_id: el id de la categoría del catálogo de arriba que mejor encaje. null si no estás razonablemente seguro
              o si ninguna encaja. NO inventes ids que no estén en el catálogo.

            NÚMEROS — copiá el VALOR exacto:
            - Formato argentino: el punto separa miles y la coma los decimales. "13.891,40" = 13891.40 ; "146,85" = 146.85.
            - Formato con coma de miles: "7,822.85" = 7822.85.
            - Devolvé siempre el número con punto decimal, sin símbolo de moneda ni separador de miles.

            Devolvé ÚNICAMENTE este objeto JSON, sin texto adicional ni markdown:
            {
              "name": string|null,
              "amount": number|null,
              "expense_date": "YYYY-MM-DD"|null,
              "supplier_name": string|null,
              "category_id": number|null
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
            throw new RuntimeException('No se pudieron interpretar los datos del comprobante. Probá con una foto más nítida.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, VariableExpenseCategory>  $categories
     * @return array{name: ?string, supplier_name: ?string, expense_date: ?string, amount: ?float, category_id: ?int}
     */
    private function normalize(array $data, Collection $categories): array
    {
        $categoryId = is_numeric($data['category_id'] ?? null) ? (int) $data['category_id'] : null;

        // Drop the suggestion if it doesn't belong to the tenant's catalog.
        if (! in_array($categoryId, $categories->pluck('id')->all(), true)) {
            $categoryId = null;
        }

        return [
            'name' => $this->toTrimmedString($data['name'] ?? null),
            'supplier_name' => $this->toTrimmedString($data['supplier_name'] ?? null),
            'expense_date' => $this->normalizeDate($data['expense_date'] ?? null),
            'amount' => $this->toFloat($data['amount'] ?? null),
            'category_id' => $categoryId,
        ];
    }

    private function toTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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
