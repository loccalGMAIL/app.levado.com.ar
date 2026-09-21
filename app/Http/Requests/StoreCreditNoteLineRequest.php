<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_line_id' => ['nullable', 'integer', 'exists:purchase_lines,id'],
            'description' => ['required_without:purchase_line_id', 'nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit' => ['required', Rule::enum(Unit::class)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'iva_rate' => ['nullable', 'numeric', 'in:0,0.105,0.21'],
            'affects_stock' => ['nullable', 'boolean'],
        ];
    }
}
