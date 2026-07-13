<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
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
}
