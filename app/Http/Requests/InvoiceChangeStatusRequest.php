<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceChangeStatusRequest extends FormRequest
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
            'data' => 'required|array',
			'data.type' => 'required|string|in:invoices',
			'data.id' => 'required|string',
			'data.attributes' => 'required|array',
			'data.attributes.status' => 'required|string|in:paid,unpaid,partial,cancelled,refunded',
        ];
    }
}
