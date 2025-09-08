<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReferenceCountRequest extends FormRequest
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
        $business = request()->route('business');

        return [
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:reference-counts'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.ref_type' => ['required', 'string', 'max:255'],
            'data.attributes.ref_count' => ['required', 'integer', 'min:0'],
            'data.attributes.business_id' => ['sometimes', 'integer', 'exists:businesses,id', function ($attribute, $value, $fail) use ($business) {
                if ($value !== $business->id) {
                    $fail('El ID del negocio no coincide con el negocio actual.');
                }
            }]
        ];
    }

    public function messages(): array
    {
        return [
            'data.required' => 'El campo de datos es obligatorio.',
            'data.type.required' => 'El tipo de recurso es obligatorio.',
            'data.type.in' => 'El tipo de recurso debe ser "reference-counts".',
            'data.attributes.required' => 'Los atributos del recurso son obligatorios.',
            'data.attributes.ref_type.required' => 'El tipo de referencia es obligatorio.',
            'data.attributes.ref_count.required' => 'El contador de referencia es obligatorio.',
            'data.attributes.business_id.exists' => 'El ID del negocio debe existir.'
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
            'data.type' => 'tipo de recurso',
            'data.attributes.ref_type' => 'tipo de referencia',
            'data.attributes.ref_count' => 'contador de referencia',
            'data.attributes.business_id' => 'ID del negocio'
        ];
    }
}
