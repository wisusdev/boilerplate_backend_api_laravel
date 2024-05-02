<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:users'],
            'data.attributes.username' => ['required', 'max:255', Rule::unique('users', 'username')],
            'data.attributes.first_name' => ['required', 'max:255'],
            'data.attributes.last_name' => ['required', 'max:255'],
            'data.attributes.email' => ['required', 'max:255', Rule::unique('users', 'email')],
            'data.attributes.password' => ['required', 'confirmed', 'min:8', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.required' => 'validation.dataRequired',
            'data.array' => 'validation.dataArray',
            'data.attributes.required' => 'validation.dataAttributesRequired',
            'data.attributes.array' => 'validation.dataAttributesArray',
            'data.type.required' => 'validation.dataTypeRequired',
            'data.type.string' => 'validation.dataTypeString',
            'data.type.in' => 'validation.dataTypeIn',
            'data.attributes.username.required' => 'validation.usernameRequired',
            'data.attributes.username.unique' => 'validation.usernameUnique',
            'data.attributes.username.max' => 'validation.usernameMax',
            'data.attributes.first_name.required' => 'validation.firstNameRequired',
            'data.attributes.first_name.max' => 'validation.firstNameMax',
            'data.attributes.last_name.required' => 'validation.lastNameRequired',
            'data.attributes.last_name.max' => 'validation.lastNameMax',
            'data.attributes.email.required' => 'validation.emailRequired',
            'data.attributes.email.unique' => 'validation.emailUnique',
            'data.attributes.email.max' => 'validation.emailMax',
            'data.attributes.password.required' => 'validation.passwordRequired',
            'data.attributes.password.confirmed' => 'validation.passwordConfirmed',
            'data.attributes.password.min' => 'validation.passwordMin',
            'data.attributes.password.max' => 'validation.passwordMax'
        ];
    }
}
