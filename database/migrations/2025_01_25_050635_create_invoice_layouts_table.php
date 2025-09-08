<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_layouts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->text('header_text')->nullable();
            $table->string('invoice_no_prefix', 191)->nullable();
            $table->string('quotation_no_prefix', 191)->nullable();
            $table->string('invoice_heading', 191)->nullable();
            $table->string('sub_heading_line1', 191)->nullable();
            $table->string('sub_heading_line2', 191)->nullable();
            $table->string('sub_heading_line3', 191)->nullable();
            $table->string('sub_heading_line4', 191)->nullable();
            $table->string('sub_heading_line5', 191)->nullable();
            $table->string('invoice_heading_not_paid', 191)->nullable();
            $table->string('invoice_heading_paid', 191)->nullable();
            $table->string('quotation_heading', 191)->nullable();
            $table->string('sub_total_label', 191)->nullable();
            $table->string('discount_label', 191)->nullable();
            $table->string('tax_label', 191)->nullable();
            $table->string('total_label', 191)->nullable();
            $table->string('round_off_label', 191)->nullable();
            $table->string('total_due_label', 191)->nullable();
            $table->string('paid_label', 191)->nullable();
            $table->boolean('show_client_id')->default(false);
            $table->string('client_id_label', 191)->nullable();
            $table->string('client_tax_label', 191)->nullable();
            $table->string('date_label', 191)->nullable();
            $table->string('date_time_format', 191)->nullable();
            $table->boolean('show_time')->default(true);
            $table->boolean('show_brand')->default(false);
            $table->boolean('show_sku')->default(true);
            $table->boolean('show_cat_code')->default(true);
            $table->boolean('show_expiry')->default(false);
            $table->boolean('show_lot')->default(false);
            $table->boolean('show_image')->default(false);
            $table->boolean('show_sale_description')->default(false);
            $table->string('sales_person_label', 191)->nullable();
            $table->boolean('show_sales_person')->default(false);
            $table->string('table_product_label', 191)->nullable();
            $table->string('table_qty_label', 191)->nullable();
            $table->string('table_unit_price_label', 191)->nullable();
            $table->string('table_subtotal_label', 191)->nullable();
            $table->string('cat_code_label', 191)->nullable();
            $table->string('logo', 191)->nullable();
            $table->boolean('show_logo')->default(false);
            $table->boolean('show_business_name')->default(false);
            $table->boolean('show_location_name')->default(true);
            $table->boolean('show_landmark')->default(true);
            $table->boolean('show_city')->default(true);
            $table->boolean('show_state')->default(true);
            $table->boolean('show_zip_code')->default(true);
            $table->boolean('show_country')->default(true);
            $table->boolean('show_mobile_number')->default(true);
            $table->boolean('show_alternate_number')->default(false);
            $table->boolean('show_email')->default(false);
            $table->boolean('show_tax_1')->default(true);
            $table->boolean('show_tax_2')->default(false);
            $table->boolean('show_barcode')->default(false);
            $table->boolean('show_payments')->default(false);
            $table->boolean('show_customer')->default(false);
            $table->string('customer_label', 191)->nullable();
            $table->string('commission_agent_label', 191)->nullable();
            $table->boolean('show_commission_agent')->default(false);
            $table->boolean('show_reward_point')->default(false);
            $table->string('highlight_color', 10)->nullable();
            $table->text('footer_text')->nullable();
            $table->text('module_info')->nullable();
            $table->text('common_settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('business_id')->index('invoice_layouts_business_id_foreign');
            $table->boolean('show_qr_code')->default(false);
            $table->text('qr_code_fields')->nullable();
            $table->string('design', 190)->nullable()->default('classic');
            $table->string('cn_heading', 191)->nullable()->comment('cn = credit note');
            $table->string('cn_no_label', 191)->nullable();
            $table->string('cn_amount_label', 191)->nullable();
            $table->text('table_tax_headings')->nullable();
            $table->boolean('show_previous_bal')->default(false);
            $table->string('prev_bal_label', 191)->nullable();
            $table->string('change_return_label', 191)->nullable();
            $table->text('product_custom_fields')->nullable();
            $table->text('contact_custom_fields')->nullable();
            $table->text('location_custom_fields')->nullable();
            $table->boolean('show_letter_head')->default(false);
            $table->string('letter_head', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_layouts');
    }
};
