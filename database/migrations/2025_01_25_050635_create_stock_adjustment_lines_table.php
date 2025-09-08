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
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->index();
            $table->unsignedInteger('product_id')->index('stock_adjustment_lines_product_id_foreign');
            $table->unsignedInteger('variation_id')->index('stock_adjustment_lines_variation_id_foreign');
            $table->decimal('quantity', 22, 4);
            $table->decimal('secondary_unit_quantity', 22, 4)->default(0);
            $table->decimal('unit_price', 22, 4)->nullable()->comment('Last purchase unit price');
            $table->integer('removed_purchase_line')->nullable();
            $table->integer('lot_no_line_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
    }
};
