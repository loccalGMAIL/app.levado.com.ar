<?php

namespace App\Http\Requests;

use App\Enums\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'yield_quantity' => ['required', 'numeric', 'min:0.001'],
            'yield_unit' => ['required', new Enum(Unit::class)],
            'is_semi_elaborate' => ['boolean'],
        ];
    }
}
