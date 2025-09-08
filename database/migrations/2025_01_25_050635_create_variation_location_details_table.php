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
        Schema::create('variation_location_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id')->index();
            $table->unsignedInteger('product_variation_id')->index()->comment('id from product_variations table');
            $table->unsignedInteger('variation_id')->index();
            $table->unsignedInteger('location_id')->index('variation_location_details_location_id_foreign');
            $table->decimal('qty_available', 22, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variation_location_details');
    }
};
