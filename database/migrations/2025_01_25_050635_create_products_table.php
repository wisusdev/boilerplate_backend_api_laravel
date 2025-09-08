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
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('products_business_id_foreign');
            $table->unsignedInteger('brand_id')->nullable()->index('products_brand_id_foreign');
            $table->unsignedInteger('category_id')->nullable()->index('products_category_id_foreign');
            $table->unsignedInteger('sub_category_id')->nullable()->index('products_sub_category_id_foreign');
            $table->unsignedInteger('tax')->nullable()->index('products_tax_foreign');
            $table->unsignedInteger('unit_id')->nullable()->index('products_unit_id_foreign');
            $table->uuid('created_by')->index('products_created_by_foreign');
            $table->string('name', 191)->index();
            $table->enum('type', ['single', 'variable', 'modifier', 'combo'])->nullable()->index();
            $table->integer('secondary_unit_id')->nullable()->index();
            $table->text('sub_unit_ids')->nullable();
            $table->enum('tax_type', ['inclusive', 'exclusive'])->index();
            $table->boolean('enable_stock')->default(false);
            $table->decimal('alert_quantity', 22, 4)->nullable();
            $table->string('sku', 191);
            $table->enum('barcode_type', ['C39', 'C128', 'EAN13', 'EAN8', 'UPCA', 'UPCE'])->nullable()->default('C128')->index();
            $table->decimal('expiry_period', 4)->nullable();
            $table->enum('expiry_period_type', ['days', 'months'])->nullable();
            $table->boolean('enable_sr_no')->default(false);
            $table->string('weight', 191)->nullable();
            $table->string('image', 191)->nullable();
            $table->text('product_description')->nullable();
            $table->string('product_custom_field1', 191)->nullable();
            $table->string('product_custom_field2', 191)->nullable();
            $table->string('product_custom_field3', 191)->nullable();
            $table->string('product_custom_field4', 191)->nullable();
            $table->string('product_custom_field5', 191)->nullable();
            $table->string('product_custom_field6', 191)->nullable();
            $table->string('product_custom_field7', 191)->nullable();
            $table->string('product_custom_field8', 191)->nullable();
            $table->string('product_custom_field9', 191)->nullable();
            $table->string('product_custom_field10', 191)->nullable();
            $table->string('product_custom_field11', 191)->nullable();
            $table->string('product_custom_field12', 191)->nullable();
            $table->string('product_custom_field13', 191)->nullable();
            $table->string('product_custom_field14', 191)->nullable();
            $table->string('product_custom_field15', 191)->nullable();
            $table->string('product_custom_field16', 191)->nullable();
            $table->string('product_custom_field17', 191)->nullable();
            $table->string('product_custom_field18', 191)->nullable();
            $table->string('product_custom_field19', 191)->nullable();
            $table->string('product_custom_field20', 191)->nullable();
            $table->integer('warranty_id')->nullable()->index();
            $table->boolean('is_inactive')->default(false);
            $table->boolean('not_for_selling')->default(false);
            $table->integer('preparation_time_in_minutes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
