<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_purchased' => ['required', 'numeric', 'min:0.0001'],
            'purchase_unit' => ['required', Rule::enum(Unit::class)],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
