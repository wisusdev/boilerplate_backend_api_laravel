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
        Schema::create('variations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191)->index();
            $table->unsignedInteger('product_id')->index('variations_product_id_foreign');
            $table->unsignedInteger('product_variation_id')->index('variations_product_variation_id_foreign');
            $table->boolean('is_active')->default(true)->comment('Indica si la variación está activa');
            $table->string('sub_sku', 191)->nullable()->index()->comment('Sub SKU para identificar variaciones de productos');
            $table->integer('variation_value_id')->nullable()->index()->comment('ID de la variación del producto');
            $table->decimal('default_purchase_price', 22, 4)->nullable()->comment('Precio de compra por defecto');
            $table->decimal('dpp_inc_tax', 22, 4)->default(0)->comment('Precio de compra por defecto con impuestos incluidos');
            $table->decimal('profit_percent', 22, 4)->default(0)->comment('Porcentaje de ganancia sobre el precio de compra');
            $table->decimal('default_sell_price', 22, 4)->nullable()->comment('Precio de venta por defecto');
            $table->decimal('sell_price_inc_tax', 22, 4)->nullable()->comment('Precio de venta con impuestos incluidos');
            $table->text('combo_variations')->nullable()->comment('Contains the combo variation details');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variations');
    }
};
