<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data.type' => ['required', 'string', 'in:change-password'],
            'data.attributes.password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'data.attributes.current_password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.type.required' => 'validation.dataTypeRequired',
            'data.type.in' => 'validation.dataTypeIn',
            'data.type.string' => 'validation.dataTypeString',
            'data.attributes.password.required' => 'validation.passwordRequired',
            'data.attributes.password.string' => 'validation.passwordString',
            'data.attributes.password.min' => 'validation.passwordMin',
            'data.attributes.password.max' => 'validation.passwordMax',
            'data.attributes.password.confirmed' => 'validation.passwordConfirmed',
            'data.attributes.current_password.required' => 'validation.currentPasswordRequired',
            'data.attributes.current_password.string' => 'validation.currentPasswordString',
            'data.attributes.current_password.min' => 'validation.currentPasswordMin',
            'data.attributes.current_password.max' => 'validation.currentPasswordMax',
        ];
    }
}
