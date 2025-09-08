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
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->index('purchase_lines_transaction_id_foreign');
            $table->unsignedInteger('product_id')->index('purchase_lines_product_id_foreign');
            $table->unsignedInteger('variation_id')->index('purchase_lines_variation_id_foreign');
            $table->decimal('quantity', 22, 4)->default(0);
            $table->decimal('secondary_unit_quantity', 22, 4)->default(0);
            $table->decimal('pp_without_discount', 22, 4)->default(0)->comment('Purchase price before inline discounts');
            $table->decimal('discount_percent', 5)->default(0)->comment('Inline discount percentage');
            $table->decimal('purchase_price', 22, 4);
            $table->decimal('purchase_price_inc_tax', 22, 4)->default(0);
            $table->decimal('item_tax', 22, 4)->comment('Tax for one quantity');
            $table->unsignedInteger('tax_id')->nullable()->index('purchase_lines_tax_id_foreign');
            $table->integer('purchase_order_line_id')->nullable();
            $table->decimal('quantity_sold', 22, 4)->default(0)->comment('Quanity sold from this purchase line');
            $table->decimal('quantity_adjusted', 22, 4)->default(0)->comment('Quanity adjusted in stock adjustment from this purchase line');
            $table->decimal('quantity_returned', 22, 4)->default(0);
            $table->decimal('po_quantity_purchased', 22, 4)->default(0);
            $table->decimal('mfg_quantity_used', 22, 4)->default(0);
            $table->date('mfg_date')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('lot_number', 191)->nullable()->index();
            $table->integer('sub_unit_id')->nullable()->index();
            $table->integer('purchase_requisition_line_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};
