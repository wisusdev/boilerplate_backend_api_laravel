<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
            'data.type' => ['required', 'string', 'in:users'],
            'data.attributes.first_name' => ['required', 'string', 'max:255'],
            'data.attributes.last_name' => ['required', 'string', 'max:255'],
            'data.attributes.roles' => ['array'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $user = $this->route('user');
            $rules['data.attributes.username'][] = ['required', 'string', 'min:3', 'max:255', Rule::unique('users', 'username')->ignore($user->id)];
            $rules['data.attributes.email'][] = ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
            $rules['data.attributes.password'] = ['nullable', 'confirmed', 'string', 'min:8', 'max:255', 'not_regex:/^$/'];
        } else {
            $rules['data.attributes.username'] = ['required', 'string', 'min:3', 'max:255', Rule::unique('users', 'username')];
            $rules['data.attributes.email'] = ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['data.attributes.password'] = ['required', 'confirmed', 'string', 'min:8', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
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
            'data.attributes.password.max' => 'validation.passwordMax',
            'data.attributes.password.string' => 'validation.passwordString',
            'data.attributes.roles.array' => 'validation.rolesArray',
        ];
    }
}
