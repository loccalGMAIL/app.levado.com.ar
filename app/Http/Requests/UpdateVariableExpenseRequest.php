<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariableExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'variable_expense_category_id' => [
                'required',
                'integer',
                Rule::exists('variable_expense_categories', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            // Sin receipt_image_path: la edición sólo adjunta o reemplaza a mano,
            // no pasa por la IA, así que nunca llega un path desde el navegador.
            'receipt' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['receipt' => 'comprobante'];
    }
}
