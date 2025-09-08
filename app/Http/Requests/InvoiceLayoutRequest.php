<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invoiceLayoutId = $this->route('invoice_layout') ? $this->route('invoice_layout')->id : null;
        $businessId = $this->input('data.attributes.business_id') ?? $this->route('business')->id;

        $data = [
            'data' => 'required|array',
            'data.type' => 'required|string|in:invoice_layouts',
            'data.attributes' => 'required|array',

            // Campos obligatorios
            'data.attributes.name' => ['required', 'string', 'max:191', Rule::unique('invoice_layouts', 'name')->where('business_id', $businessId)->ignore($invoiceLayoutId)],

            // Campos opcionales con sus validaciones
            'data.attributes.header_text' => 'nullable|string',
            'data.attributes.invoice_no_prefix' => 'nullable|string|max:191',
            'data.attributes.quotation_no_prefix' => 'nullable|string|max:191',
            'data.attributes.invoice_heading' => 'nullable|string|max:191',
            'data.attributes.sub_heading_line1' => 'nullable|string|max:191',
            'data.attributes.sub_heading_line2' => 'nullable|string|max:191',
            'data.attributes.sub_heading_line3' => 'nullable|string|max:191',
            'data.attributes.sub_heading_line4' => 'nullable|string|max:191',
            'data.attributes.sub_heading_line5' => 'nullable|string|max:191',
            'data.attributes.invoice_heading_not_paid' => 'nullable|string|max:191',
            'data.attributes.invoice_heading_paid' => 'nullable|string|max:191',
            'data.attributes.quotation_heading' => 'nullable|string|max:191',
            'data.attributes.sub_total_label' => 'nullable|string|max:191',
            'data.attributes.discount_label' => 'nullable|string|max:191',
            'data.attributes.tax_label' => 'nullable|string|max:191',
            'data.attributes.total_label' => 'nullable|string|max:191',
            'data.attributes.round_off_label' => 'nullable|string|max:191',
            'data.attributes.total_due_label' => 'nullable|string|max:191',
            'data.attributes.paid_label' => 'nullable|string|max:191',
            'data.attributes.show_client_id' => 'boolean',
            'data.attributes.client_id_label' => 'nullable|string|max:191',
            'data.attributes.client_tax_label' => 'nullable|string|max:191',
            'data.attributes.date_label' => 'nullable|string|max:191',
            'data.attributes.date_time_format' => 'nullable|string|max:191',
            'data.attributes.show_time' => 'boolean',
            'data.attributes.show_brand' => 'boolean',
            'data.attributes.show_sku' => 'boolean',
            'data.attributes.show_cat_code' => 'boolean',
            'data.attributes.show_expiry' => 'boolean',
            'data.attributes.show_lot' => 'boolean',
            'data.attributes.show_image' => 'boolean',
            'data.attributes.show_sale_description' => 'boolean',
            'data.attributes.sales_person_label' => 'nullable|string|max:191',
            'data.attributes.show_sales_person' => 'boolean',
            'data.attributes.table_product_label' => 'nullable|string|max:191',
            'data.attributes.table_qty_label' => 'nullable|string|max:191',
            'data.attributes.table_unit_price_label' => 'nullable|string|max:191',
            'data.attributes.table_subtotal_label' => 'nullable|string|max:191',
            'data.attributes.cat_code_label' => 'nullable|string|max:191',
            'data.attributes.logo' => 'nullable|string|max:191',
            'data.attributes.show_logo' => 'boolean',
            'data.attributes.show_business_name' => 'boolean',
            'data.attributes.show_location_name' => 'boolean',
            'data.attributes.show_landmark' => 'boolean',
            'data.attributes.show_city' => 'boolean',
            'data.attributes.show_state' => 'boolean',
            'data.attributes.show_zip_code' => 'boolean',
            'data.attributes.show_country' => 'boolean',
            'data.attributes.show_mobile_number' => 'boolean',
            'data.attributes.show_alternate_number' => 'boolean',
            'data.attributes.show_email' => 'boolean',
            'data.attributes.show_tax_1' => 'boolean',
            'data.attributes.show_tax_2' => 'boolean',
            'data.attributes.show_barcode' => 'boolean',
            'data.attributes.show_payments' => 'boolean',
            'data.attributes.show_customer' => 'boolean',
            'data.attributes.customer_label' => 'nullable|string|max:191',
            'data.attributes.commission_agent_label' => 'nullable|string|max:191',
            'data.attributes.show_commission_agent' => 'boolean',
            'data.attributes.show_reward_point' => 'boolean',
            'data.attributes.highlight_color' => 'nullable|string|max:10',
            'data.attributes.footer_text' => 'nullable|string',
            'data.attributes.module_info' => 'nullable|string',
            'data.attributes.common_settings' => 'nullable|string',
            'data.attributes.is_default' => 'boolean',
            'data.attributes.show_qr_code' => 'boolean',
            'data.attributes.qr_code_fields' => 'nullable|string',
            'data.attributes.design' => ['nullable', 'string', 'max:190', Rule::in(['classic', 'modern', 'compact'])],
            'data.attributes.cn_heading' => 'nullable|string|max:191',
            'data.attributes.cn_no_label' => 'nullable|string|max:191',
            'data.attributes.cn_amount_label' => 'nullable|string|max:191',
            'data.attributes.table_tax_headings' => 'nullable|string',
            'data.attributes.show_previous_bal' => 'boolean',
            'data.attributes.prev_bal_label' => 'nullable|string|max:191',
            'data.attributes.change_return_label' => 'nullable|string|max:191',
            'data.attributes.product_custom_fields' => 'nullable|string',
            'data.attributes.contact_custom_fields' => 'nullable|string',
            'data.attributes.location_custom_fields' => 'nullable|string',
            'data.attributes.show_letter_head' => 'boolean',
            'data.attributes.letter_head' => 'nullable|string|max:191',
        ];

        if ($this->isMethod('post')) {
            $data['data.attributes.business_id'] = 'required|integer|exists:businesses,id';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $data['data.id'] = 'required|integer|exists:invoice_layouts,id';
            $data['data.attributes.business_id'] = 'prohibited'; // El ID del negocio no debe actualizarse
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'data.required' => 'Los datos son requeridos.',
            'data.type.required' => 'El tipo de recurso es requerido.',
            'data.type.in' => 'El tipo de recurso debe ser invoice_layouts.',
            'data.attributes.required' => 'Los atributos son requeridos.',
            'data.attributes.business_id.required' => 'El ID del negocio es requerido.',
            'data.attributes.business_id.exists' => 'El negocio especificado no existe.',
            'data.attributes.name.required' => 'El nombre del diseño es requerido.',
            'data.attributes.name.unique' => 'Ya existe un diseño con este nombre en el negocio.',
            'data.attributes.name.max' => 'El nombre no puede exceder 191 caracteres.',
            'data.attributes.design.in' => 'El diseño debe ser classic, modern o compact.',
            'data.attributes.highlight_color.max' => 'El color de resaltado no puede exceder 10 caracteres.',
        ];
    }
}
