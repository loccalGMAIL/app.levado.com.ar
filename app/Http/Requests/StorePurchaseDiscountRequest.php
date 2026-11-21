<?php

namespace App\Http\Requests;

use App\Enums\DiscountKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un descuento de la compra. Sin purchase_line_id alcanza a
 * toda la factura; con él, sólo a ese renglón (que tiene que ser de esta compra).
 */
class StorePurchaseDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $purchaseId = $this->route('purchase')?->id;

        return [
            'description' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(DiscountKind::class)],
            'rate' => ['required_if:kind,percent', 'nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required_if:kind,amount', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'purchase_line_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_lines', 'id')->where('purchase_id', $purchaseId),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'descripción',
            'kind' => 'tipo',
            'rate' => 'porcentaje',
            'amount' => 'importe',
            'purchase_line_id' => 'renglón',
        ];
    }
}
