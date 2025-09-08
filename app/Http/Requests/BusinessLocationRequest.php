<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $data = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:business_locations',
            'data.attributes' => 'required|array',
            'data.attributes.invoice_scheme_id' => 'required|integer|exists:invoice_schemes,id',
            'data.attributes.invoice_layout_id' => 'required|integer|exists:invoice_layouts,id',
            'data.attributes.name' => 'required|string|max:256',
            'data.attributes.location_id' => 'nullable|string|max:191',
            'data.attributes.country' => 'required|string|max:100',
            'data.attributes.state' => 'required|string|max:100',
            'data.attributes.city' => 'required|string|max:100',
            'data.attributes.zip_code' => 'required|string|max:7',
            'data.attributes.landmark' => 'nullable|string',

            'data.attributes.sale_invoice_layout_id' => 'nullable|integer|exists:invoice_layouts,id',
            'data.attributes.sale_invoice_scheme_id' => 'nullable|integer|exists:invoice_schemes,id',
            'data.attributes.mobile' => 'nullable|string|max:191',
            'data.attributes.alternate_number' => 'nullable|string|max:191',
            'data.attributes.email' => 'nullable|email|max:191',
            'data.attributes.website' => 'nullable|url|max:191',
            'data.attributes.receipt_printer_type' => 'nullable|in:browser,printer',
            'data.attributes.printer_id' => 'nullable|integer|exists:printers,id',
            'data.attributes.selling_price_group_id' => 'nullable|integer|exists:selling_price_groups,id',
            'data.attributes.print_receipt_on_invoice' => 'nullable|boolean',
            'data.attributes.default_payment_accounts' => 'nullable|string',
            'data.attributes.featured_products' => 'nullable|string',
            'data.attributes.is_active' => 'nullable|boolean',
            'data.attributes.custom_field1' => 'nullable|string|max:191',
            'data.attributes.custom_field2' => 'nullable|string|max:191',
            'data.attributes.custom_field3' => 'nullable|string|max:191',
            'data.attributes.custom_field4' => 'nullable|string|max:191'
        ];

        if ($this->isMethod('post')) {
            $data['data.attributes.business_id'] = 'required|integer|exists:businesses,id';   
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $data['data.id'] = 'required|string|exists:business_locations,id';
            $data['data.attributes.business_id'] = 'prohibited'; // Business ID should not be updated
        }

        return $data;
    }
}
