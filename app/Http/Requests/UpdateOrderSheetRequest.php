<?php

namespace App\Http\Requests;

use App\Models\OrderSheet;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var OrderSheet $sheet */
        $sheet = $this->route('orderSheet');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('order_sheets', 'name')
                    ->where('tenant_id', app(Tenant::class)->id)
                    ->ignore($sheet->id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Ya tenés una planilla con ese nombre.'];
    }
}
