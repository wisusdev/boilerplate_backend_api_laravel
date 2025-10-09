<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceLayoutResource extends JsonResource
{
    use JsonApiResource;

    /**
     * Transform the resource into an array.
     */
    public function toJsonApi(): array
    {
        return [
            'business_id' => $this->resource->business_id,
            'name' => $this->resource->name,
            'header_text' => $this->resource->header_text,
            'invoice_no_prefix' => $this->resource->invoice_no_prefix,
            'quotation_no_prefix' => $this->resource->quotation_no_prefix,
            'invoice_heading' => $this->resource->invoice_heading,
            'sub_heading_line1' => $this->resource->sub_heading_line1,
            'sub_heading_line2' => $this->resource->sub_heading_line2,
            'sub_heading_line3' => $this->resource->sub_heading_line3,
            'sub_heading_line4' => $this->resource->sub_heading_line4,
            'sub_heading_line5' => $this->resource->sub_heading_line5,
            'invoice_heading_not_paid' => $this->resource->invoice_heading_not_paid,
            'invoice_heading_paid' => $this->resource->invoice_heading_paid,
            'quotation_heading' => $this->resource->quotation_heading,
            'sub_total_label' => $this->resource->sub_total_label,
            'discount_label' => $this->resource->discount_label,
            'tax_label' => $this->resource->tax_label,
            'total_label' => $this->resource->total_label,
            'round_off_label' => $this->resource->round_off_label,
            'total_due_label' => $this->resource->total_due_label,
            'paid_label' => $this->resource->paid_label,
            'show_client_id' => $this->resource->show_client_id,
            'client_id_label' => $this->resource->client_id_label,
            'client_tax_label' => $this->resource->client_tax_label,
            'date_label' => $this->resource->date_label,
            'date_time_format' => $this->resource->date_time_format,
            'show_time' => $this->resource->show_time,
            'show_brand' => $this->resource->show_brand,
            'show_sku' => $this->resource->show_sku,
            'show_cat_code' => $this->resource->show_cat_code,
            'show_expiry' => $this->resource->show_expiry,
            'show_lot' => $this->resource->show_lot,
            'show_image' => $this->resource->show_image,
            'show_sale_description' => $this->resource->show_sale_description,
            'sales_person_label' => $this->resource->sales_person_label,
            'show_sales_person' => $this->resource->show_sales_person,
            'table_product_label' => $this->resource->table_product_label,
            'table_qty_label' => $this->resource->table_qty_label,
            'table_unit_price_label' => $this->resource->table_unit_price_label,
            'table_subtotal_label' => $this->resource->table_subtotal_label,
            'cat_code_label' => $this->resource->cat_code_label,
            'logo' => $this->resource->logo,
            'show_logo' => $this->resource->show_logo,
            'show_business_name' => $this->resource->show_business_name,
            'show_location_name' => $this->resource->show_location_name,
            'show_landmark' => $this->resource->show_landmark,
            'show_city' => $this->resource->show_city,
            'show_state' => $this->resource->show_state,
            'show_zip_code' => $this->resource->show_zip_code,
            'show_country' => $this->resource->show_country,
            'show_mobile_number' => $this->resource->show_mobile_number,
            'show_alternate_number' => $this->resource->show_alternate_number,
            'show_email' => $this->resource->show_email,
            'show_tax_1' => $this->resource->show_tax_1,
            'show_tax_2' => $this->resource->show_tax_2,
            'show_barcode' => $this->resource->show_barcode,
            'show_payments' => $this->resource->show_payments,
            'show_customer' => $this->resource->show_customer,
            'customer_label' => $this->resource->customer_label,
            'commission_agent_label' => $this->resource->commission_agent_label,
            'show_commission_agent' => $this->resource->show_commission_agent,
            'show_reward_point' => $this->resource->show_reward_point,
            'highlight_color' => $this->resource->highlight_color,
            'footer_text' => $this->resource->footer_text,
            'module_info' => $this->resource->module_info,
            'common_settings' => $this->resource->common_settings,
            'is_default' => $this->resource->is_default,
            'show_qr_code' => $this->resource->show_qr_code,
            'qr_code_fields' => $this->resource->qr_code_fields,
            'design' => $this->resource->design,
            'cn_heading' => $this->resource->cn_heading,
            'cn_no_label' => $this->resource->cn_no_label,
            'cn_amount_label' => $this->resource->cn_amount_label,
            'table_tax_headings' => $this->resource->table_tax_headings,
            'show_previous_bal' => $this->resource->show_previous_bal,
            'prev_bal_label' => $this->resource->prev_bal_label,
            'change_return_label' => $this->resource->change_return_label,
            'product_custom_fields' => $this->resource->product_custom_fields,
            'contact_custom_fields' => $this->resource->contact_custom_fields,
            'location_custom_fields' => $this->resource->location_custom_fields,
            'show_letter_head' => $this->resource->show_letter_head,
            'letter_head' => $this->resource->letter_head,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    public function getRelationshipLinks(): array
    {
        return [
            [
                'name' => 'business',
                'route' => 'businesses.show',
                'params' => $this->resource->business
            ]
        ];
    }

    public function getIncludes(): array
    {
        return [
            BusinessResource::make($this->resource->business)
        ];
    }

    protected function getRouteParameters(): array
    {
        return [
            'business' => $this->resource->business,
            'invoice_layout' => $this->resource
        ];
    }
}
