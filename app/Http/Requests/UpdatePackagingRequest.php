<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackagingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'subdivisions' => ['nullable', 'integer', 'min:2'],
            'subdivision_label' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'subdivisions' => 'unidades por presentación',
            'subdivision_label' => 'nombre de la unidad',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'subdivisions.min' => 'Si el envase no se subdivide, dejá «Unidades por presentación» vacío. Poné un número sólo cuando trae 2 o más unidades.',
        ];
    }
}
