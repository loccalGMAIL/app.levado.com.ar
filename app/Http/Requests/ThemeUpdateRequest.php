<?php

namespace App\Http\Requests;

use App\Enums\Theme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThemeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme' => ['nullable', Rule::enum(Theme::class)],
        ];
    }

    /**
     * El tema elegido, o null para «Automático».
     */
    public function theme(): ?Theme
    {
        return $this->enum('theme', Theme::class);
    }
}
