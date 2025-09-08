<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $currencyId = $isUpdate ? $this->route('currency')->id : null;

        $rules = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:currencies',
            'data.attributes' => 'required|array',

            // Atributos principales
            'data.attributes.country' => 'required|string|max:100',
            'data.attributes.country_code' => ['required', 'string', 'max:10', Rule::unique('currencies', 'country_code')->ignore($currencyId)],
            'data.attributes.currency' => 'required|string|max:100',
            'data.attributes.code' => 'required|string|max:25',
            'data.attributes.symbol' => 'required|string|max:25',
            'data.attributes.thousand_separator' => 'required|string|max:10',
            'data.attributes.decimal_separator' => 'required|string|max:10',
        ];

        // Para PATCH requests, el campo id es requerido según JSON API spec
        if ($isUpdate) {
            $rules['data.id'] = 'required|string';
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'data.attributes.country' => 'país',
            'data.attributes.country_code' => 'código de país',
            'data.attributes.currency' => 'moneda',
            'data.attributes.code' => 'código',
            'data.attributes.symbol' => 'símbolo',
            'data.attributes.thousand_separator' => 'separador de miles',
            'data.attributes.decimal_separator' => 'separador decimal',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.required' => 'Los datos son requeridos.',
            'data.array' => 'Los datos deben ser un arreglo.',
            'data.type.required' => 'El tipo de datos es requerido.',
            'data.type.in' => 'El tipo de datos debe ser "currencies".',
            'data.attributes.required' => 'Los atributos son requeridos.',
            'data.attributes.array' => 'Los atributos deben ser un arreglo.',
            'data.attributes.country.required' => 'El país es requerido.',
            'data.attributes.country.max' => 'El país no puede tener más de 100 caracteres.',
            'data.attributes.country_code.required' => 'El código de país es requerido.',
            'data.attributes.country_code.max' => 'El código de país no puede tener más de 10 caracteres.',
            'data.attributes.country_code.unique' => 'El código de país ya está en uso.',
            'data.attributes.currency.required' => 'La moneda es requerida.',
            'data.attributes.currency.max' => 'La moneda no puede tener más de 100 caracteres.',
            'data.attributes.code.required' => 'El código es requerido.',
            'data.attributes.code.max' => 'El código no puede tener más de 25 caracteres.',
            'data.attributes.code.unique' => 'El código ya está en uso.',
            'data.attributes.symbol.required' => 'El símbolo es requerido.',
            'data.attributes.symbol.max' => 'El símbolo no puede tener más de 25 caracteres.',
            'data.attributes.thousand_separator.required' => 'El separador de miles es requerido.',
            'data.attributes.thousand_separator.max' => 'El separador de miles no puede tener más de 10 caracteres.',
            'data.attributes.decimal_separator.required' => 'El separador decimal es requerido.',
            'data.attributes.decimal_separator.max' => 'El separador decimal no puede tener más de 10 caracteres.',
        ];
    }
}
