<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:businesses'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.name' => ['required', 'string', 'max:191'],
            'data.attributes.currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'data.attributes.time_zone' => ['required', 'string'],
            'data.attributes.fy_start_month' => ['integer', 'between:1,12'],
            'data.attributes.accounting_method' => ['string', 'in:fifo,lifo,avco'],
            'data.attributes.sell_price_tax' => ['string', 'in:includes,excludes'],
            'data.attributes.expiry_type' => ['string', 'in:add_expiry,add_manufacturing'],
            'data.attributes.on_product_expiry' => ['string', 'in:keep_selling,stop_selling,auto_delete'],
            'data.attributes.currency_symbol_placement' => ['string', 'in:before,after'],
            'data.attributes.date_format' => ['string', 'max:191'],
            'data.attributes.time_format' => ['string', 'in:12,24'],
            'data.attributes.rp_expiry_type' => ['string', 'in:month,year'],
            'data.attributes.sales_cmsn_agnt' => ['nullable', 'string', 'in:logged_in_user,user,cmsn_agnt'],

            // Campos opcionales
            'data.attributes.start_date' => ['nullable', 'date'],
            'data.attributes.tax_number_1' => ['nullable', 'string', 'max:100'],
            'data.attributes.tax_label_1' => ['nullable', 'string', 'max:10'],
            'data.attributes.tax_number_2' => ['nullable', 'string', 'max:100'],
            'data.attributes.tax_label_2' => ['nullable', 'string', 'max:10'],
            'data.attributes.code_label_1' => ['nullable', 'string', 'max:191'],
            'data.attributes.code_1' => ['nullable', 'string', 'max:191'],
            'data.attributes.code_label_2' => ['nullable', 'string', 'max:191'],
            'data.attributes.code_2' => ['nullable', 'string', 'max:191'],
            'data.attributes.default_sales_tax' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'data.attributes.purchase_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'data.attributes.default_profit_percent' => ['numeric', 'min:0', 'max:100'],
            'data.attributes.default_sales_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'data.attributes.transaction_edit_days' => ['integer', 'min:0'],
            'data.attributes.stock_expiry_alert_days' => ['integer', 'min:0'],
            'data.attributes.stop_selling_before' => ['nullable', 'integer', 'min:0'],
            'data.attributes.p_exchange_rate' => ['numeric', 'min:0.001'],
            'data.attributes.logo' => ['nullable', 'string', 'max:191'],
            'data.attributes.sku_prefix' => ['nullable', 'string', 'max:191'],
            'data.attributes.default_unit' => ['nullable', 'integer', 'exists:units,id'],
            'data.attributes.currency_precision' => ['integer', 'between:0,4'],
            'data.attributes.quantity_precision' => ['integer', 'between:0,4'],

            // Campos boolean
            'data.attributes.enable_product_expiry' => ['boolean'],
            'data.attributes.enable_tooltip' => ['boolean'],
            'data.attributes.purchase_in_diff_currency' => ['boolean'],
            'data.attributes.enable_brand' => ['boolean'],
            'data.attributes.enable_category' => ['boolean'],
            'data.attributes.enable_sub_category' => ['boolean'],
            'data.attributes.enable_price_tax' => ['boolean'],
            'data.attributes.enable_purchase_status' => ['boolean'],
            'data.attributes.enable_lot_number' => ['boolean'],
            'data.attributes.enable_sub_units' => ['boolean'],
            'data.attributes.enable_racks' => ['boolean'],
            'data.attributes.enable_row' => ['boolean'],
            'data.attributes.enable_position' => ['boolean'],
            'data.attributes.enable_editing_product_from_purchase' => ['boolean'],
            'data.attributes.item_addition_method' => ['boolean'],
            'data.attributes.enable_inline_tax' => ['boolean'],
            'data.attributes.enable_rp' => ['boolean'],
            'data.attributes.is_active' => ['boolean'],

            // Campos de reward points
            'data.attributes.rp_name' => ['nullable', 'string', 'max:191'],
            'data.attributes.amount_for_unit_rp' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.min_order_total_for_rp' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.max_rp_per_order' => ['nullable', 'integer', 'min:0'],
            'data.attributes.redeem_amount_per_unit_rp' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.min_order_total_for_redeem' => ['nullable', 'numeric', 'min:0'],
            'data.attributes.min_redeem_point' => ['nullable', 'integer', 'min:0'],
            'data.attributes.max_redeem_point' => ['nullable', 'integer', 'min:0'],
            'data.attributes.rp_expiry_period' => ['nullable', 'integer', 'min:0'],

            // Campos de texto
            'data.attributes.keyboard_shortcuts' => ['nullable', 'json'],
            'data.attributes.pos_settings' => ['nullable', 'json'],
            'data.attributes.weighing_scale_setting' => ['nullable', 'json'],
            'data.attributes.essentials_settings' => ['nullable', 'json'],
            'data.attributes.enabled_modules' => ['nullable', 'json'],
            'data.attributes.ref_no_prefixes' => ['nullable', 'json'],
            'data.attributes.theme_color' => ['nullable', 'string', 'max:20'],
            'data.attributes.email_settings' => ['nullable', 'json'],
            'data.attributes.sms_settings' => ['nullable', 'json'],
            'data.attributes.custom_labels' => ['nullable', 'json'],
            'data.attributes.common_settings' => ['nullable', 'json'],
        ];

        // Validaciones específicas para actualización
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $business = $this->route('business');

            // Validar que el negocio pertenezca al usuario autenticado o tenga permisos
            $rules['data.attributes.name'][] = Rule::unique('businesses', 'name')->ignore($business->id);
        } else {
            $rules['data.attributes.name'][] = Rule::unique('businesses', 'name');
        }

        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'data.type.required' => 'El tipo de recurso es requerido.',
            'data.type.in' => 'El tipo de recurso debe ser "businesses".',
            'data.attributes.name.required' => 'El nombre del negocio es requerido.',
            'data.attributes.name.unique' => 'Ya existe un negocio con este nombre.',
            'data.attributes.currency_id.required' => 'La moneda es requerida.',
            'data.attributes.currency_id.exists' => 'La moneda seleccionada no existe.',
            'data.attributes.time_zone.required' => 'La zona horaria es requerida.',
            'data.attributes.default_sales_tax.exists' => 'El impuesto seleccionado no existe.',
            'data.attributes.purchase_currency_id.exists' => 'La moneda de compra seleccionada no existe.',
            'data.attributes.default_unit.exists' => 'La unidad predeterminada seleccionada no existe.',
            'data.attributes.fy_start_month.between' => 'El mes de inicio del año fiscal debe estar entre 1 y 12.',
            'data.attributes.currency_precision.between' => 'La precisión de moneda debe estar entre 0 y 4.',
            'data.attributes.quantity_precision.between' => 'La precisión de cantidad debe estar entre 0 y 4.',
            'data.attributes.default_profit_percent.min' => 'El porcentaje de ganancia no puede ser negativo.',
            'data.attributes.default_profit_percent.max' => 'El porcentaje de ganancia no puede ser mayor a 100.',
        ];
    }
}
