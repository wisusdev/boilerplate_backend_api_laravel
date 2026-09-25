<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Services\Dte\FacturaBuilder;
use App\Services\InvoiceBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // CAT-022: 13 DUI, 36 NIT, 03 pasaporte, 02 carnet de residente, 37 otro.
            'data.attributes.receptor_document_type' => ['sometimes', 'nullable', 'string', Rule::in(['13', '36', '03', '02', '37'])],
            'data.attributes.receptor_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'data.attributes.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'data.attributes.issued_at' => ['sometimes', 'nullable', 'date'],
            'data.attributes.status' => ['sometimes', 'string', Rule::in(['pending', 'issued', 'paid', 'cancelled'])],

            // Documento de venta: 01 Factura (consumidor final) o 03 CCF (contribuyente).
            'data.attributes.dte_type' => ['sometimes', 'nullable', Rule::in(['01', '03'])],
            'data.attributes.receptor_nrc' => ['sometimes', 'nullable', 'string', 'max:10'],
            'data.attributes.receptor_cod_actividad' => ['sometimes', 'nullable', 'string', 'max:6'],
            'data.attributes.receptor_nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:150'],
            'data.attributes.receptor_departamento' => ['sometimes', 'nullable', 'string', 'max:2'],
            'data.attributes.receptor_municipio' => ['sometimes', 'nullable', 'string', 'max:2'],
            'data.attributes.receptor_distrito' => ['sometimes', 'nullable', 'string', 'max:2'],
            'data.attributes.receptor_direccion' => ['sometimes', 'nullable', 'string', 'max:200'],
            'data.attributes.receptor_telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'data.attributes.receptor_agente_retencion' => ['sometimes', 'boolean'],

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
     * Un CCF necesita al receptor completo y en códigos de catálogo: se
     * comprueba al guardar para que quien factura lo corrija con el cliente
     * delante, y no al emitir.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $attrs = (array) $this->input('data.attributes', []);
            $actual = $this->route('invoice');
            $invoice = $actual instanceof Invoice ? (clone $actual) : new Invoice;
            $invoice->forceFill(array_intersect_key($attrs, array_flip([
                'dte_type', 'receptor_name', 'receptor_document', 'receptor_email', ...InvoiceBuilder::RECEPTOR_FISCAL,
            ])));

            if ($invoice->dte_type === FacturaBuilder::TIPO_CCF) {
                // Una clave por error: la respuesta JSON:API muestra uno por campo.
                foreach (FacturaBuilder::receptorCcfErrors($invoice) as $i => $error) {
                    $v->errors()->add("data.attributes.receptor.{$i}", $error);
                }
            }
        });
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
