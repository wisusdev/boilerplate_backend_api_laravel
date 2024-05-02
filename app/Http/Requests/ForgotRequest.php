<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotRequest extends FormRequest
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
            'data.attributes.email' => ['required', 'email', 'exists:users,email']
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
            'data.attributes.email.required' => 'validation.emailRequired',
            'data.attributes.email.email' => 'validation.emailEmail',
            'data.attributes.email.exists' => 'validation.emailExists'
        ];
    }
}
