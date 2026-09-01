<?php

namespace App\Http\Requests;

use App\Models\PaymentLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos con los que un agente da por cobrado un enlace tras cotejarlo en el
 * portal del banco.
 */
class PaymentLinkConfirmRequest extends FormRequest
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
        /** @var PaymentLink|null $link */
        $link = $this->route('paymentLink');

        return [
            'data' => ['required', 'array'],
            'data.attributes' => ['required', 'array'],

            // Número de autorización del banco: es la prueba de que alguien miró
            // el cobro de verdad y la clave para cuadrar contra el extracto.
            'data.attributes.authorization' => ['required', 'string', 'min:4', 'max:40'],

            'data.attributes.charged' => ['required', 'numeric', 'gt:0'],

            // Fecha del cargo en el banco, no la de hoy. Un cobro no puede ser
            // del futuro ni anterior a la existencia del propio enlace.
            'data.attributes.paid_at' => array_filter([
                'required', 'date', 'before_or_equal:now',
                $link?->created_at ? 'after_or_equal:'.$link->created_at->toDateString() : null,
            ]),

            'data.attributes.note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.attributes.paid_at.after_or_equal' => 'La fecha del cobro no puede ser anterior a la emisión del enlace.',
            'data.attributes.paid_at.before_or_equal' => 'La fecha del cobro no puede estar en el futuro.',
        ];
    }
}
