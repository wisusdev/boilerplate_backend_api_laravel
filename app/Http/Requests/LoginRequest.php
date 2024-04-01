<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'data.email' => ['required', 'email'],
            'data.password' => ['required'],
            'data.device_name' => ['required']
        ];
    }

    public function messages(): array
    {
        return [
            'data.email.required' => 'Email is required',
            'data.email.email' => 'Email is invalid',
            'data.password.required' => 'Password is required',
            'data.device_name.required' => 'Device name is required'
        ];
    }
}
