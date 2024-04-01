<?php

namespace App\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data.username' => ['required', 'max:255', Rule::unique('users', 'username')],
            'data.first_name' => ['required', 'max:255'],
            'data.last_name' => ['required', 'max:255'],
            'data.email' => ['required', 'max:255', Rule::unique('users', 'email')],
            'data.password' => ['required', 'confirmed', 'min:8', 'max:128'],
        ];
    }

    public function messages()
    {
        return [
            'data.username.required' => 'Username is required',
            'data.username.unique' => 'Username is already taken',
            'data.username.max' => 'Username is too long',
            'data.first_name.required' => 'First name is required',
            'data.first_name.max' => 'First name is too long',
            'data.last_name.required' => 'Last name is required',
            'data.last_name.max' => 'Last name is too long',
            'data.email.required' => 'Email is required',
            'data.email.unique' => 'Email is already taken',
            'data.email.max' => 'Email is too long',
            'data.password.required' => 'Password is required',
            'data.password.confirmed' => 'Passwords do not match',
            'data.password.min' => 'Password is too short',
            'data.password.max' => 'Password is too long'
        ];
    }
}
