<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchaseable_type' => ['required', 'in:ingredient,packaging'],
            'purchaseable_id' => ['required', 'integer'],
            'quantity_purchased' => ['required', 'numeric', 'min:0.0001'],
            'purchase_unit' => ['required', Rule::enum(Unit::class)],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
