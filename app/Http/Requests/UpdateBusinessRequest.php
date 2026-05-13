<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'productive_hours_month' => ['required', 'integer', 'min:1', 'max:744'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
