<?php

namespace App\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.attributes.username' => ['required', 'max:255', Rule::unique('users', 'username')],
            'data.attributes.first_name' => ['required', 'max:255'],
            'data.attributes.last_name' => ['required', 'max:255'],
            'data.attributes.email' => ['required', 'max:255', Rule::unique('users', 'email')],
            'data.attributes.password' => ['required', 'confirmed', 'min:8', 'max:128'],
        ];
    }

    public function messages()
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
            'data.attributes.password.max' => 'Password is too long'
        ];
    }
}
