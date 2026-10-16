<?php

namespace App\Http\Requests;

use App\Enums\Jurisdiction;
use App\Enums\PerceptionBase;
use App\Enums\PerceptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una percepción de la compra. Con perception_concept_id se
 * toman tipo, provincia y nombre del catálogo; sin él se cargan a mano.
 */
class StorePurchasePerceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perception_concept_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(PerceptionType::class)],
            'jurisdiction' => ['nullable', Rule::enum(Jurisdiction::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'base_type' => ['nullable', Rule::enum(PerceptionBase::class)],
            'base_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    public function attributes(): array
    {
        return ['base_amount' => 'base', 'rate' => 'alícuota', 'amount' => 'importe', 'description' => 'descripción'];
    }

    /**
     * Sin concepto del catálogo hace falta al menos una descripción y un valor
     * (alícuota o importe) para que la fila tenga sentido.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $hasConcept = filled($this->input('perception_concept_id'));

                if (! $hasConcept && blank($this->input('description'))) {
                    $validator->errors()->add('description', 'Elegí un concepto o escribí una descripción.');
                }

                if (blank($this->input('rate')) && blank($this->input('amount')) && ! $hasConcept) {
                    $validator->errors()->add('amount', 'Cargá la alícuota o el importe.');
                }
            },
        ];
    }
}
