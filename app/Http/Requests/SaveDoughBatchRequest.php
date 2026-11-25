<?php

namespace App\Http\Requests;

use App\Enums\WasteReason;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guardado del borrador de un amasado (y, con `confirm`, su confirmación):
 * renglones con lo real y la merma, sobrante o faltante de cada masa, notas.
 */
class SaveDoughBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenantId = app(Tenant::class)->id;

        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['sometimes', 'boolean'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'lines.*.actual_units' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lines.*.waste_units' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lines.*.waste_reason' => ['nullable', Rule::in(array_map(fn (WasteReason $reason) => $reason->value, WasteReason::forStage(WasteReason::STAGE_DOUGH)))],
            'lines.*.waste_note' => ['nullable', 'string', 'max:255'],
            'masses' => ['sometimes', 'array', 'max:100'],
            'masses.*.recipe_id' => ['required', 'integer'],
            'masses.*.leftover_quantity' => ['nullable', 'numeric', 'min:-99999', 'max:99999'],
        ];
    }

    /**
     * Una merma lleva motivo; «Otro» lleva además el texto.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('lines', []) as $index => $line) {
                    if (! is_array($line) || (float) ($line['waste_units'] ?? 0) <= 0) {
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
}
