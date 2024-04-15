<?php

namespace App\Http\Requests;

use App\Rules\Base64ValidationRule;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'data.attributes.first_name.required' => 'First name is required',
            'data.attributes.last_name.required' => 'Last name is required',
            'data.attributes.email.required' => 'Email is required',
            'data.attributes.email.email' => 'Email is invalid',
            'data.attributes.email.unique' => 'Email is already taken',
            'data.attributes.avatar.string' => 'Avatar must be a string',
            'data.attributes.language.required' => 'Language is required',
            'data.attributes.language.in' => 'Language is invalid'
        ];
    }
}
