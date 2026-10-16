<?php

namespace App\Http\Requests;

use App\Enums\Jurisdiction;
use App\Enums\PerceptionBase;
use App\Enums\PerceptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerceptionConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PerceptionType::class)],
            'jurisdiction' => [
                Rule::requiredIf(fn () => PerceptionType::tryFrom((string) $this->input('type'))?->requiresJurisdiction() === true),
                'nullable',
                Rule::enum(Jurisdiction::class),
            ],
            'name' => ['required', 'string', 'max:255'],
            'default_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_type' => ['nullable', Rule::enum(PerceptionBase::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['jurisdiction' => 'provincia', 'name' => 'nombre', 'default_rate' => 'alícuota', 'base_type' => 'base de cálculo'];
    }

    /**
     * Sólo IIBB es provincial: en cualquier otro tipo la provincia se descarta.
     *
     * @return array<string, mixed>
     */
    public function conceptData(): array
    {
        $data = $this->validated();

        // Un base_type vacío no debe pisar el default de la columna con NULL.
        if (blank($data['base_type'] ?? null)) {
            unset($data['base_type']);
        }

        if (! PerceptionType::from($data['type'])->requiresJurisdiction()) {
            $data['jurisdiction'] = null;
        }

        return $data;
    }
}
