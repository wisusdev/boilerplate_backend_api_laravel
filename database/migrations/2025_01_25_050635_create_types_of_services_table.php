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
        Schema::create('types_of_services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->integer('business_id')->index();
            $table->text('location_price_group')->nullable();
            $table->decimal('packing_charge', 22, 4)->nullable();
            $table->enum('packing_charge_type', ['fixed', 'percent'])->nullable();
            $table->boolean('enable_custom_fields')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('types_of_services');
    }
};
