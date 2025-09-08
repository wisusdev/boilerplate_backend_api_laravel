<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->route('business')->id;
        $taxRateId = $this->route('taxRate')?->id;

        return [
            'data' => 'required|array',
            'data.type' => 'required|string|in:tax-rates',
            'data.id' => $this->isMethod('patch') ? 'required|string' : 'sometimes|string',
            'data.attributes' => 'required|array',
            'data.attributes.name' => ['required', 'string', 'max:191', Rule::unique('tax_rates', 'name')->where('business_id', $businessId)
                    ->ignore($taxRateId)
                    ->whereNull('deleted_at')
            ],
            'data.attributes.amount' => 'required|numeric|min:0|max:100',
            'data.attributes.is_tax_group' => 'sometimes|boolean',
            'data.attributes.for_tax_group' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'data.type' => 'tipo de datos',
            'data.id' => 'ID',
            'data.attributes.name' => 'nombre',
            'data.attributes.amount' => 'porcentaje',
            'data.attributes.is_tax_group' => 'es grupo de impuestos',
            'data.attributes.for_tax_group' => 'para grupo de impuestos',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.type.in' => 'El tipo de datos debe ser "tax-rates".',
            'data.attributes.name.unique' => 'Ya existe una tasa de impuesto con ese nombre en este negocio.',
            'data.attributes.amount.min' => 'El porcentaje no puede ser menor a 0.',
            'data.attributes.amount.max' => 'El porcentaje no puede ser mayor a 100.',
        ];
    }
}
