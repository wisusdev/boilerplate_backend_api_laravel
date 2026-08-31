<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:currencies'],
            // En edición el código sigue siendo único, pero ignorando la propia
            // moneda; antes solo existía el alta y no hacía falta distinguir.
            'data.attributes.code' => [
                'required', 'string', 'size:3',
                Rule::unique('currencies', 'code')->ignore($this->route('currency')?->id),
            ],
            'data.attributes.name' => ['required', 'string', 'max:255'],
            'data.attributes.symbol' => ['required', 'string', 'max:8'],
            'data.attributes.rate_to_usd' => ['required', 'numeric', 'gt:0'],
            'data.attributes.is_default' => ['sometimes', 'boolean'],
            'data.attributes.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
