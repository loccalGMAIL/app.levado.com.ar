<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'note_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('credit_notes', 'note_number')
                    ->where('tenant_id', app(Tenant::class)->id)
                    ->where('supplier_id', (int) $this->input('supplier_id'))
                    ->ignore($this->route('creditNote')),
            ],
            'note_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'note_number.unique' => 'Ya existe una nota de crédito con este número para este proveedor.',
        ];
    }
}
