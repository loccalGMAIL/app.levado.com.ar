<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScannedPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required', 'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['required', 'date'],
            'invoice_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'invoice_image_path' => ['nullable', 'string', 'max:255'],

            'lines' => ['array'],
            'lines.*.include' => ['nullable', 'boolean'],
            'lines.*.raw_name' => ['nullable', 'string', 'max:255'],
            'lines.*.matched_type' => ['nullable', 'in:ingredient,packaging'],
            'lines.*.matched_id' => ['nullable', 'integer'],
            'lines.*.quantity_purchased' => ['nullable', 'numeric', 'min:0.0001'],
            'lines.*.purchase_unit' => ['nullable', Rule::enum(Unit::class)],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.iva_rate' => ['nullable', 'numeric', 'in:0,0.105,0.21'],
        ];
    }
}
