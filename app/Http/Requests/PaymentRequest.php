<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
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
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:payments'],

            'data.attributes.payable_type' => ['required', 'string', Rule::in(['booking'])],
            'data.attributes.payable_id' => ['required', 'integer'],
            'data.attributes.gateway' => ['required', 'string', Rule::in(['paypal', 'stripe', 'manual'])],
            'data.attributes.method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'data.attributes.amount' => ['required', 'numeric', 'min:0'],
            'data.attributes.currency_code' => ['sometimes', 'string', 'size:3'],
            'data.attributes.transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.attributes.status' => ['sometimes', 'string', Rule::in(['pending', 'paid', 'failed'])],
            'data.attributes.payload' => ['sometimes', 'array'],
        ];
    }
}
