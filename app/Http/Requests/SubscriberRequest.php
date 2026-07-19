<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data'                       => ['required', 'array'],
            'data.attributes'            => ['required', 'array'],
            'data.type'                  => ['required', 'string', 'in:subscribers'],
            'data.attributes.email'      => ['required', 'email', 'max:255'],
            'data.attributes.name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.source'     => ['sometimes', 'nullable', 'string', 'max:60'],
            'data.attributes.locale'     => ['sometimes', 'nullable', 'string', 'max:5'],
        ];
    }
}
