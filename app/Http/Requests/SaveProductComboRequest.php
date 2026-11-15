<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un combo: los datos del artículo y sus componentes.
 */
class SaveProductComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenantId = app(Tenant::class)->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')
                    ->where('tenant_id', $tenantId)
                    ->ignore($this->route('combo')?->id),
            ],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            // Un combo no puede llevar otro combo adentro, ni un artículo dado de baja.
            'lines.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->whereNot('type', ProductType::Combo->value),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required' => 'Agregá los artículos que componen el combo.',
            'lines.*.product_id.exists' => 'Uno de los artículos no es válido: tiene que estar activo y no ser otro combo.',
            'lines.*.product_id.distinct' => 'Hay un artículo repetido: sumá la cantidad en un solo renglón.',
            'lines.*.quantity.gt' => 'La cantidad de cada artículo tiene que ser mayor a cero.',
        ];
    }

    /**
     * Un combo agrupa al menos dos unidades: dos artículos distintos, o más de
     * una unidad del mismo.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = $this->input('lines', []);

            if (count($lines) === 1 && (float) reset($lines)['quantity'] <= 1) {
                $validator->errors()->add('lines', 'Un combo lleva al menos dos artículos, o más de una unidad del mismo.');
            }
        }];
    }
}
