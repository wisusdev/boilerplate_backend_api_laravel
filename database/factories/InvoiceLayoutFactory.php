<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class InvoiceLayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->text(191),
            'business_id' => Business::inRandomOrder()->first()->id,
            'header_text' => fake()->paragraph(),
            'invoice_no_prefix' => fake()->text(191),
            'quotation_no_prefix' => fake()->text(191),
            'invoice_heading' => fake()->text(191),
            'sub_heading_line1' => fake()->text(191),
            'sub_heading_line2' => fake()->text(191),
            'sub_heading_line3' => fake()->text(191),
            'sub_heading_line4' => fake()->text(191),
            'sub_heading_line5' => fake()->text(191),
            'invoice_heading_not_paid' => fake()->text(191),
            'invoice_heading_paid' => fake()->text(191),
            'quotation_heading' => fake()->text(191),
            'sub_total_label' => fake()->text(191),
            'discount_label' => fake()->text(191),
            'tax_label' => fake()->text(191),
            'total_label' => fake()->text(191),
            'round_off_label' => fake()->text(191),
            'total_due_label' => fake()->text(191),
            'paid_label' => fake()->text(191),
            'show_client_id' => fake()->boolean(),
            'client_id_label' => fake()->text(191),
            'client_tax_label' => fake()->text(191),
            'date_label' => fake()->text(191),
            'date_time_format' => fake()->text(191),
            'show_time' => fake()->boolean(),
            'show_brand' => fake()->boolean(),
            'show_sku' => fake()->boolean(),
            'show_cat_code' => fake()->boolean(),
            'show_expiry' => fake()->boolean(),
            'show_lot' => fake()->boolean(),
            'show_image' => fake()->boolean(),
            'show_sale_description' => fake()->boolean(),
            'sales_person_label' => fake()->text(191),
            'show_sales_person' => fake()->boolean(),
            'table_product_label' => fake()->text(191),
            'table_qty_label' => fake()->text(191),
            'table_unit_price_label' => fake()->text(191),
            'table_subtotal_label' => fake()->text(191),
            'cat_code_label' => fake()->text(191),
            'logo' => fake()->text(191),
            'show_logo' => fake()->boolean(),
            'show_business_name' => fake()->boolean(),
            'show_location_name' => fake()->boolean(),
            'show_landmark' => fake()->boolean(),
            'show_city' => fake()->boolean(),
            'show_state' => fake()->boolean(),
            'show_zip_code' => fake()->boolean(),
            'show_country' => fake()->boolean(),
            'show_mobile_number' => fake()->boolean(),
            'show_alternate_number' => fake()->boolean(),
            'show_email' => fake()->boolean(),
            'show_tax_1' => fake()->boolean(),
            'show_tax_2' => fake()->boolean(),
            'show_barcode' => fake()->boolean(),
            'show_payments' => fake()->boolean(),
            'show_customer' => fake()->boolean(),
            'customer_label' => fake()->text(191),
            'commission_agent_label' => fake()->text(191),
            'show_commission_agent' => fake()->boolean(),
            'show_reward_point' => fake()->boolean(),
            'highlight_color' => fake()->text(10),
            'footer_text' => fake()->paragraph(),
            'module_info' => fake()->paragraph(),
            'common_settings' => fake()->paragraph(),
            'is_default' => fake()->boolean(),
            'show_letter_head' => fake()->boolean(),
            'letter_head' => fake()->text(191),
            'show_qr_code' => fake()->boolean(),
            'qr_code_fields' => fake()->paragraph(),
            'design' => fake()->text(190),
            'cn_heading' => fake()->text(191),
            'cn_no_label' => fake()->text(191),
            'cn_amount_label' => fake()->text(191),
            'table_tax_headings' => fake()->paragraph(),
            'show_previous_bal' => fake()->boolean(),
            'prev_bal_label' => fake()->text(191),
            'change_return_label' => fake()->text(191),
            'product_custom_fields' => fake()->paragraph(),
            'contact_custom_fields' => fake()->paragraph(),
            'location_custom_fields' => fake()->paragraph(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
