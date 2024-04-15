<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'data.attributes.password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'data.attributes.current_password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.attributes.password.required' => 'The password field is required.',
            'data.attributes.password.string' => 'The password must be a string.',
            'data.attributes.password.min' => 'The password must be at least 8 characters.',
            'data.attributes.password.max' => 'The password must not be greater than 100 characters.',
            'data.attributes.password.confirmed' => 'The password confirmation does not match.',
            'data.attributes.current_password.required' => 'The current password field is required.',
            'data.attributes.current_password.string' => 'The current password must be a string.',
            'data.attributes.current_password.min' => 'The current password must be at least 8 characters.',
            'data.attributes.current_password.max' => 'The current password must not be greater than 100 characters.',
        ];
    }
}
