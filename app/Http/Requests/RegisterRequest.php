<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'username' => 'required|unique:users|max:255',
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'email' => 'required|unique:users|max:255',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'max:128'
            ],
        ];
    }

    public function messages()
    {
        return [
            'username.required' => 'Username is required',
            'username.unique' => 'Username is already taken',
            'username.max' => 'Username is too long',
            'first_name.required' => 'First name is required',
            'first_name.max' => 'First name is too long',
            'last_name.required' => 'Last name is required',
            'last_name.max' => 'Last name is too long',
            'email.required' => 'Email is required',
            'email.unique' => 'Email is already taken',
            'email.max' => 'Email is too long',
            'password.required' => 'Password is required',
            'password.confirmed' => 'Passwords do not match',
            'password.min' => 'Password is too short',
            'password.max' => 'Password is too long'
        ];
    }
}
