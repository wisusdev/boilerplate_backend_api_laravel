<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invoiceSchemeId = $this->route('invoice_scheme') ? $this->route('invoice_scheme')->id : null;
        $businessId = $this->input('data.attributes.business_id') ?? $this->route('business')->id;

        $data = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:invoice_schemes',
            'data.attributes' => 'required|array',

            // Campos obligatorios
            'data.attributes.name' => ['required', 'string', 'max:191', Rule::unique('invoice_schemes', 'name')->where('business_id', $businessId)->ignore($invoiceSchemeId)],
            'data.attributes.scheme_type' => ['required', 'string', Rule::in(['blank', 'year'])],
            'data.attributes.number_type' => ['required', 'string', 'max:100'],

            // Campos opcionales
            'data.attributes.prefix' => 'nullable|string|max:191',
            'data.attributes.start_number' => 'nullable|integer|min:1',
            'data.attributes.total_digits' => 'nullable|integer|min:1|max:10',
            'data.attributes.is_default' => 'boolean',
        ];

        if ($this->isMethod('post')) {
            $data['data.attributes.business_id'] = 'required|integer|exists:businesses,id';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $data['data.id'] = 'required|integer|exists:invoice_schemes,id';
            $data['data.attributes.business_id'] = 'prohibited'; // El ID del negocio no debe actualizarse
        }

        return $data;

    }

    public function messages(): array
    {
        return [
            'data.required' => 'Los datos son requeridos.',
            'data.type.required' => 'El tipo de recurso es requerido.',
            'data.type.in' => 'El tipo de recurso debe ser invoice_schemes.',
            'data.attributes.required' => 'Los atributos son requeridos.',
            'data.attributes.business_id.required' => 'El ID del negocio es requerido.',
            'data.attributes.business_id.exists' => 'El negocio especificado no existe.',
            'data.attributes.name.required' => 'El nombre del esquema es requerido.',
            'data.attributes.name.unique' => 'Ya existe un esquema con este nombre en el negocio.',
            'data.attributes.name.max' => 'El nombre no puede exceder 191 caracteres.',
            'data.attributes.scheme_type.required' => 'El tipo de esquema es requerido.',
            'data.attributes.scheme_type.in' => 'El tipo de esquema debe ser blank o year.',
            'data.attributes.number_type.required' => 'El tipo de numeración es requerido.',
            'data.attributes.number_type.max' => 'El tipo de numeración no puede exceder 100 caracteres.',
            'data.attributes.prefix.max' => 'El prefijo no puede exceder 191 caracteres.',
            'data.attributes.start_number.min' => 'El número inicial debe ser mayor a 0.',
            'data.attributes.total_digits.min' => 'El total de dígitos debe ser mayor a 0.',
            'data.attributes.total_digits.max' => 'El total de dígitos no puede exceder 10.',
            'data.attributes.is_default.boolean' => 'El campo is_default debe ser verdadero o falso.',
        ];
    }
}
