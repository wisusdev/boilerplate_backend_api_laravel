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
        Schema::create('transaction_sell_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->index('transaction_sell_lines_transaction_id_foreign');
            $table->unsignedInteger('product_id')->index('transaction_sell_lines_product_id_foreign');
            $table->unsignedInteger('variation_id')->index('transaction_sell_lines_variation_id_foreign');
            $table->decimal('quantity', 22, 4)->default(0);
            $table->decimal('secondary_unit_quantity', 22, 4)->default(0);
            $table->decimal('quantity_returned', 20, 4)->default(0);
            $table->decimal('unit_price_before_discount', 22, 4)->default(0);
            $table->decimal('unit_price', 22, 4)->nullable()->comment('Sell price excluding tax');
            $table->enum('line_discount_type', ['fixed', 'percentage'])->nullable()->index();
            $table->decimal('line_discount_amount', 22, 4)->default(0);
            $table->decimal('unit_price_inc_tax', 22, 4)->nullable()->comment('Sell price including tax');
            $table->decimal('item_tax', 22, 4)->comment('Tax for one quantity');
            $table->unsignedInteger('tax_id')->nullable()->index('transaction_sell_lines_tax_id_foreign');
            $table->integer('discount_id')->nullable()->index();
            $table->integer('lot_no_line_id')->nullable()->index();
            $table->text('sell_line_note')->nullable();
            $table->integer('so_line_id')->nullable();
            $table->decimal('so_quantity_invoiced', 22, 4)->default(0);
            $table->integer('res_service_staff_id')->nullable();
            $table->string('res_line_order_status', 191)->nullable();
            $table->integer('parent_sell_line_id')->nullable()->index();
            $table->string('children_type', 191)->default('')->index()->comment('Type of children for the parent, like modifier or combo');
            $table->integer('sub_unit_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_sell_lines');
    }
};
