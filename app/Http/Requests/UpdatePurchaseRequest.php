<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('purchases', 'invoice_number')
                    ->where('tenant_id', app(Tenant::class)->id)
                    ->where('supplier_id', (int) $this->input('supplier_id'))
                    ->ignore($this->route('purchase')),
            ],
            'invoice_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'default_iva_rate' => ['nullable', 'numeric', 'in:0,0.105,0.21'],
            'default_percepcion_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'invoice' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['invoice' => 'comprobante'];
    }

    public function messages(): array
    {
        return [
            'invoice_number.unique' => 'Ya existe una compra con este número de factura para este proveedor.',
        ];
    }
}
