<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    use JsonApiResource;
    public function toJsonApi(): array
    {
        return [
            'name' => $this->resource->name,
            'currency_id' => $this->resource->currency_id,
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'tax_number_1' => $this->resource->tax_number_1,
            'tax_label_1' => $this->resource->tax_label_1,
            'tax_number_2' => $this->resource->tax_number_2,
            'tax_label_2' => $this->resource->tax_label_2,
            'code_label_1' => $this->resource->code_label_1,
            'code_1' => $this->resource->code_1,
            'code_label_2' => $this->resource->code_label_2,
            'code_2' => $this->resource->code_2,
            'default_sales_tax' => $this->resource->default_sales_tax,
            'default_profit_percent' => $this->resource->default_profit_percent,
            'owner_id' => $this->resource->owner_id,
            'time_zone' => $this->resource->time_zone,
            'fy_start_month' => $this->resource->fy_start_month,
            'accounting_method' => $this->resource->accounting_method,
            'default_sales_discount' => $this->resource->default_sales_discount,
            'sell_price_tax' => $this->resource->sell_price_tax,
            'logo' => $this->resource->logo,
            'sku_prefix' => $this->resource->sku_prefix,
            'enable_product_expiry' => $this->resource->enable_product_expiry,
            'expiry_type' => $this->resource->expiry_type,
            'on_product_expiry' => $this->resource->on_product_expiry,
            'stop_selling_before' => $this->resource->stop_selling_before,
            'enable_tooltip' => $this->resource->enable_tooltip,
            'purchase_in_diff_currency' => $this->resource->purchase_in_diff_currency,
            'purchase_currency_id' => $this->resource->purchase_currency_id,
            'p_exchange_rate' => $this->resource->p_exchange_rate,
            'transaction_edit_days' => $this->resource->transaction_edit_days,
            'stock_expiry_alert_days' => $this->resource->stock_expiry_alert_days,
            'keyboard_shortcuts' => $this->resource->keyboard_shortcuts ? json_decode($this->resource->keyboard_shortcuts, true) : null,
            'pos_settings' => $this->resource->pos_settings ? json_decode($this->resource->pos_settings, true) : null,
            'weighing_scale_setting' => $this->resource->weighing_scale_setting ? json_decode($this->resource->weighing_scale_setting, true) : null,
            'essentials_settings' => $this->resource->essentials_settings ? json_decode($this->resource->essentials_settings, true) : null,
            'enable_brand' => $this->resource->enable_brand,
            'enable_category' => $this->resource->enable_category,
            'enable_sub_category' => $this->resource->enable_sub_category,
            'enable_price_tax' => $this->resource->enable_price_tax,
            'enable_purchase_status' => $this->resource->enable_purchase_status,
            'enable_lot_number' => $this->resource->enable_lot_number,
            'default_unit' => $this->resource->default_unit,
            'enable_sub_units' => $this->resource->enable_sub_units,
            'enable_racks' => $this->resource->enable_racks,
            'enable_row' => $this->resource->enable_row,
            'enable_position' => $this->resource->enable_position,
            'enable_editing_product_from_purchase' => $this->resource->enable_editing_product_from_purchase,
            'sales_cmsn_agnt' => $this->resource->sales_cmsn_agnt,
            'item_addition_method' => $this->resource->item_addition_method,
            'enable_inline_tax' => $this->resource->enable_inline_tax,
            'currency_symbol_placement' => $this->resource->currency_symbol_placement,
            'enabled_modules' => $this->resource->enabled_modules ? json_decode($this->resource->enabled_modules, true) : null,
            'date_format' => $this->resource->date_format,
            'time_format' => $this->resource->time_format,
            'ref_no_prefixes' => $this->resource->ref_no_prefixes ? json_decode($this->resource->ref_no_prefixes, true) : null,
            'theme_color' => $this->resource->theme_color,
            'created_by' => $this->resource->created_by,
            'enable_rp' => $this->resource->enable_rp,
            'rp_name' => $this->resource->rp_name,
            'amount_for_unit_rp' => $this->resource->amount_for_unit_rp,
            'min_order_total_for_rp' => $this->resource->min_order_total_for_rp,
            'max_rp_per_order' => $this->resource->max_rp_per_order,
            'redeem_amount_per_unit_rp' => $this->resource->redeem_amount_per_unit_rp,
            'min_order_total_for_redeem' => $this->resource->min_order_total_for_redeem,
            'min_redeem_point' => $this->resource->min_redeem_point,
            'max_redeem_point' => $this->resource->max_redeem_point,
            'rp_expiry_period' => $this->resource->rp_expiry_period,
            'rp_expiry_type' => $this->resource->rp_expiry_type,
            'email_settings' => $this->resource->email_settings ? json_decode($this->resource->email_settings, true) : null,
            'sms_settings' => $this->resource->sms_settings ? json_decode($this->resource->sms_settings, true) : null,
            'custom_labels' => $this->resource->custom_labels ? json_decode($this->resource->custom_labels, true) : null,
            'common_settings' => $this->resource->common_settings ? json_decode($this->resource->common_settings, true) : null,
            'is_active' => $this->resource->is_active,
            'currency_precision' => $this->resource->currency_precision,
            'quantity_precision' => $this->resource->quantity_precision,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    public function getResourceLinks(): array
    {
        return [
            'self' => route('businesses.show', $this->resource->getRouteKey()),
        ];
    }
}
