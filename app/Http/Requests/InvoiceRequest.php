<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
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
        $creando = $this->isMethod('post');

        return [
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:invoices'],
            'data.attributes' => ['required', 'array'],

            'data.attributes.receptor_name' => [$creando ? 'required' : 'sometimes', 'nullable', 'string', 'max:250'],
            'data.attributes.receptor_document' => ['sometimes', 'nullable', 'string', 'max:50'],
            'data.attributes.receptor_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'data.attributes.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'data.attributes.issued_at' => ['sometimes', 'nullable', 'date'],
            'data.attributes.status' => ['sometimes', 'string', Rule::in(['pending', 'issued', 'paid', 'cancelled'])],

            // Conceptos. Al crear hace falta al menos uno: una factura sin líneas
            // no tiene importe y saldría en blanco.
            'data.attributes.items' => [$creando ? 'required' : 'sometimes', 'array', 'min:1', 'max:50'],
            'data.attributes.items.*.description' => ['sometimes', 'nullable', 'string', 'max:250'],
            'data.attributes.items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'data.attributes.items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'data.attributes.items.*.tour_id' => ['sometimes', 'nullable', 'integer', 'exists:tours,id'],
            'data.attributes.items.*.transport_vehicle_id' => ['sometimes', 'nullable', 'integer', 'exists:transport_vehicles,id'],
        ];
    }

    /**
     * El controlador y el builder trabajan con los atributos planos.
     *
     * @return array<string,mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $attrs = parent::validated()['data']['attributes'] ?? [];

        // `validated()` reconstruye los arrays con comodín siguiendo el orden de
        // las REGLAS, no el del payload: una línea sin `description` acababa
        // detrás de las que sí la traían, y los conceptos salían desordenados en
        // la factura. Se reponen desde la entrada, que ya está validada.
        if (array_key_exists('items', $attrs)) {
            $attrs['items'] = array_values((array) $this->input('data.attributes.items', []));
        }

        return $attrs;
    }
}
