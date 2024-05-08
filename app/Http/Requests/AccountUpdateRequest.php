<?php

namespace App\Http\Requests;

use App\Rules\Base64ValidationRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountUpdateRequest extends FormRequest
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
            'data.type' => ['required', 'string', 'in:profile'],
            'data.attributes.first_name' => ['required', 'string'],
            'data.attributes.last_name' => ['required', 'string'],
            'data.attributes.email' => ['required', 'email', 'unique:users,email,' . $this->user()->id],
            'data.attributes.avatar' => ['nullable', 'string', new Base64ValidationRule()],
            'data.attributes.language' => ['required', 'string', 'in:en,es']
        ];
    }

    public function messages(): array
    {
        return [
            'data.type.required' => 'validation.dataTypeRequired',
            'data.type.in' => 'validation.dataTypeIn',
            'data.type.string' => 'validation.dataTypeString',
            'data.attributes.first_name.required' => 'validation.firstNameRequired',
            'data.attributes.last_name.required' => 'validation.lastNameRequired',
            'data.attributes.email.required' => 'validation.emailRequired',
            'data.attributes.email.email' => 'validation.emailEmail',
            'data.attributes.email.unique' => 'validation.emailUnique',
            'data.attributes.avatar.string' => 'validation.avatarString',
            'data.attributes.language.required' => 'validation.languageRequired',
            'data.attributes.language.in' => 'validation.languageIn'
        ];
    }
}
