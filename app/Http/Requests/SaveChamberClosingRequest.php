<?php

namespace App\Http\Requests;

use App\Enums\WasteReason;
use App\Models\Product;
use App\Models\Tenant;
use App\Rules\TrayQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Lo contado en el cierre de cámara (y, con `confirm`, su confirmación).
 * `counted` viene en la medida del artículo: latas o bandejas de a cuartos
 * si su receta tiene rinde, unidades si no. La merma va siempre en unidades.
 */
class SaveChamberClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'confirm' => ['sometimes', 'boolean'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'lines.*.counted' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lines.*.waste_units' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lines.*.waste_reason' => ['nullable', Rule::in(array_map(fn (WasteReason $reason) => $reason->value, WasteReason::forStage(WasteReason::STAGE_OVEN)))],
            'lines.*.waste_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Latas de a cuartos; una merma lleva motivo y «Otro», el texto.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lines = (array) $this->input('lines', []);
                $withContainer = $this->productsWithContainer($lines);

                foreach ($lines as $index => $line) {
                    if (! is_array($line)) {
                        continue;
                    }

                    $counted = $line['counted'] ?? null;

                    if (isset($withContainer[(int) ($line['product_id'] ?? 0)]) && is_numeric($counted) && ! TrayQuantity::isStep((float) $counted)) {
                        $validator->errors()->add("lines.{$index}.counted", 'Las latas y bandejas se cuentan de a cuartos (por ejemplo 1, 1,25, 1,5 o 1,75).');
                    }

                    if ((float) ($line['waste_units'] ?? 0) <= 0) {
                        continue;
                    }

                    $reason = WasteReason::tryFrom((string) ($line['waste_reason'] ?? ''));

                    if ($reason === null) {
                        $validator->errors()->add("lines.{$index}.waste_reason", 'Elegí el motivo de la merma.');
                    } elseif ($reason->requiresNote() && blank($line['waste_note'] ?? null)) {
                        $validator->errors()->add("lines.{$index}.waste_note", 'Contá cuál fue el motivo de la merma.');
                    }
                }
            },
        ];
    }

    /**
     * Ids de los artículos del pedido que se cuentan en latas o bandejas.
     *
     * @param  array<int|string, mixed>  $lines
     * @return array<int, true>
     */
    private function productsWithContainer(array $lines): array
    {
        $ids = collect($lines)
            ->map(fn ($line) => is_array($line) && is_numeric($line['product_id'] ?? null) ? (int) $line['product_id'] : null)
            ->filter()
            ->all();

        return Product::query()
            ->where('tenant_id', app(Tenant::class)->id)
            ->whereIn('id', $ids)
            ->with('recipe')
            ->get()
            ->filter(fn (Product $product) => $product->containerType() !== null)
            ->mapWithKeys(fn (Product $product) => [$product->id => true])
            ->all();
    }
}
