<?php

namespace App\Http\Requests;

use App\Rules\BankPaymentLinkUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentLinkAttachRequest extends FormRequest
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
            'data.attributes.url' => ['required', 'string', 'max:500', new BankPaymentLinkUrl],
            // Caducidad opcional: si el banco no permite fijarla, la nuestra
            // sigue sirviendo para saber cuándo dejar de esperar.
            'data.attributes.expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
