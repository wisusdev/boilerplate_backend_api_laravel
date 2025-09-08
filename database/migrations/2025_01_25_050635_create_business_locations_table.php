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
        Schema::create('business_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('invoice_scheme_id')->index('business_locations_invoice_scheme_id_foreign')->comment('Esquema de facturación predeterminado para la ubicación del negocio');
            $table->unsignedInteger('invoice_layout_id')->index('business_locations_invoice_layout_id_foreign')->comment('Diseño de factura predeterminado para la ubicación del negocio');
            $table->string('location_id', 191)->nullable();
            $table->enum('receipt_printer_type', ['browser', 'printer'])->default('browser')->index();
            $table->integer('sale_invoice_layout_id')->nullable()->index()->comment('Diseño de factura de venta predeterminado para la ubicación del negocio');
            $table->integer('selling_price_group_id')->nullable()->index();
            $table->integer('printer_id')->nullable()->index();
            $table->string('name', 256);
            $table->text('landmark')->nullable();
            $table->string('country', 100);
            $table->string('state', 100);
            $table->string('city', 100);
            $table->char('zip_code', 7);
            $table->integer('sale_invoice_scheme_id')->nullable();
            $table->text('default_payment_accounts')->nullable();
            $table->boolean('print_receipt_on_invoice')->nullable()->default(true);
            $table->string('mobile', 191)->nullable();
            $table->string('alternate_number', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('website', 191)->nullable();
            $table->text('featured_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('custom_field1', 191)->nullable();
            $table->string('custom_field2', 191)->nullable();
            $table->string('custom_field3', 191)->nullable();
            $table->string('custom_field4', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_locations');
    }
};
