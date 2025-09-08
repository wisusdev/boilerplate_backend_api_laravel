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
        Schema::create('variation_group_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('variation_id')->index('variation_group_prices_variation_id_foreign');
            $table->unsignedInteger('price_group_id')->index('variation_group_prices_price_group_id_foreign');
            $table->decimal('price_inc_tax', 22, 4);
            $table->string('price_type', 191)->default('fixed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variation_group_prices');
    }
};
