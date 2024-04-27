<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RolRequest extends FormRequest
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
        $rules = [
            'data.attributes.role.name' => ['required', 'string', 'max:50', 'min:3'],
            'data.attributes.permissions' => ['array'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $role = $this->route('role');
            $rules['data.attributes.role.name'][] = 'unique:roles,name,' . $role->uuid . ',uuid';
        } else {
            $rules['data.attributes.role.name'][] = 'unique:roles,name';
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'data.attributes.role.name.required' => 'El campo nombre es requerido',
            'data.attributes.role.name.string' => 'El campo nombre debe ser un texto',
            'data.attributes.role.name.max' => 'El campo nombre debe tener máximo 50 caracteres',
            'data.attributes.role.name.min' => 'El campo nombre debe tener mínimo 3 caracteres',
            'data.attributes.permissions.array' => 'El campo permisos debe ser un array',
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $messages['data.attributes.role.name.unique'] = 'El nombre del rol ya existe y debe ser único';
        } else {
            $messages['data.attributes.role.name.unique'] = 'El campo nombre ya existe';
        }

        return $messages;
    }
}
