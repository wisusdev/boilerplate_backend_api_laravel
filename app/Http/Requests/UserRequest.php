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
            'data.attributes.username.required' => 'Username is required',
            'data.attributes.username.unique' => 'Username is already taken',
            'data.attributes.username.max' => 'Username is too long',
            'data.attributes.first_name.required' => 'First name is required',
            'data.attributes.first_name.max' => 'First name is too long',
            'data.attributes.last_name.required' => 'Last name is required',
            'data.attributes.last_name.max' => 'Last name is too long',
            'data.attributes.email.required' => 'Email is required',
            'data.attributes.email.unique' => 'Email is already taken',
            'data.attributes.email.max' => 'Email is too long',
            'data.attributes.password.required' => 'Password is required',
            'data.attributes.password.confirmed' => 'Passwords do not match',
            'data.attributes.password.min' => 'Password is too short',
            'data.attributes.password.max' => 'Password is too long',
            'data.attributes.password.string' => 'Password must be a string',
            'data.attributes.roles.array' => 'Roles must be an array'
        ];
    }
}
