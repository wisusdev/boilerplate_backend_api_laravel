<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:roles'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.name' => ['required', 'string', 'max:50', 'min:3'],
            'data.attributes.permissions' => ['array'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $role = $this->route('role');
            $rules['data.attributes.name'][] = 'unique:roles,name,' . $role->uuid . ',uuid';
        } else {
            $rules['data.attributes.name'][] = 'unique:roles,name';
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'data.attributes.name.required' => 'validation.nameRequired',
            'data.attributes.name.string' => 'validation.nameString',
            'data.attributes.name.max' => 'validation.nameMax',
            'data.attributes.name.min' => 'validation.nameMin',
            'data.attributes.name.unique' => 'validation.nameUnique',
            'data.attributes.permissions.required' => 'validation.permissionsRequired',
            'data.attributes.permissions.array' => 'validation.permissionsArray',

        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $messages['data.attributes.role.name.unique'] = 'El nombre del rol ya existe y debe ser único';
        } else {
            $messages['data.attributes.role.name.unique'] = 'El campo nombre ya existe';
        }

        return $messages;
    }
}
