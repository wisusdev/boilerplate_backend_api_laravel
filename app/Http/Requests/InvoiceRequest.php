<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
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
			'data' => ['required', 'array'],
			'data.type' => ['required', 'string', 'in:invoices'],
			'data.attributes' => ['required', 'array'],
			'data.attributes.user_id' => ['required', 'exists:users,id'],
			'data.attributes.created_by' => ['required', 'exists:users,id'],
			'data.attributes.invoice_date' => ['required', 'date'],
			'data.attributes.due_date' => ['nullable', 'date'],
			'data.attributes.total_amount' => ['required', 'numeric'],
			'data.attributes.status' => ['required', 'string', 'in:paid,unpaid,partial'],
			'data.attributes.payment_method' => ['required', 'string', 'in:paypal,stripe,wompi'],
			'data.attributes.order_confirmation' => ['required', 'boolean'],
			'data.attributes.generate_invoice' => ['required', 'boolean'],
			'data.attributes.send_email' => ['required', 'boolean'],

			'data.attributes.items' => ['required', 'array'],
			'data.attributes.items.*.item_id' => ['required', 'string'],
			'data.attributes.items.*.type' => ['required', 'string', 'in:products,packages'],
			'data.attributes.items.*.description' => ['required', 'string'],
			'data.attributes.items.*.quantity' => ['required', 'numeric'],
			'data.attributes.items.*.unit_price' => ['required', 'numeric'],
			'data.attributes.items.*.total_price' => ['required', 'numeric']
        ];
    }
}
