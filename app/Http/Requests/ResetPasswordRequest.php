<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'data.type' => ['required', 'string', 'in:reset-password'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.token' => ['required', 'string'],
            // Mismo mínimo que en el registro: sin esto se podía dejar la cuenta
            // con una contraseña de un carácter a través del reseteo.
            'data.attributes.password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
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
            'data.attributes.token.required' => 'validation.tokenRequired',
            'data.attributes.token.string' => 'validation.tokenString',
            'data.attributes.password.required' => 'validation.passwordRequired',
            'data.attributes.password.string' => 'validation.passwordString',
            'data.attributes.password.confirmed' => 'validation.passwordConfirmed',
            'data.attributes.password.min' => 'validation.passwordMin',
            'data.attributes.password.max' => 'validation.passwordMax',
        ];
    }
}
