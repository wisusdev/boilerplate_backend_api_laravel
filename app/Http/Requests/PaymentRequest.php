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
        // NO se aceptan del cliente: `amount` y `currency_code` se derivan del saldo
        // pendiente de la reserva, y `transaction_reference` / `payload` los fija la
        // pasarela. Aceptarlos permitía pagar 0,00 y darse la reserva por pagada.
        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:payments'],

            'data.attributes.payable_type' => ['required', 'string', Rule::in(['booking'])],
            'data.attributes.payable_id' => ['required', 'integer'],
            // 'manual' cubre efectivo (único método manual que queda) y lo que
            // registra el back-office a mano; wompi/bac_link nunca pasan por
            // aquí, nacen en /payments/checkout.
            'data.attributes.gateway' => ['required', 'string', Rule::in(['manual', 'whatsapp'])],
            'data.attributes.method' => ['sometimes', 'nullable', 'string', 'max:50'],
            // `status` solo lo honra el controlador si el usuario tiene
            // 'payments:mark-paid'; para el resto el pago nace en 'pending'.
            'data.attributes.status' => ['sometimes', 'string', Rule::in(['pending', 'paid'])],
        ];
    }
}
