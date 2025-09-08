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
        Schema::create('discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->integer('business_id')->index();
            $table->integer('brand_id')->nullable()->index();
            $table->integer('category_id')->nullable()->index();
            $table->integer('location_id')->nullable()->index();
            $table->integer('priority')->nullable()->index();
            $table->string('discount_type', 191)->nullable();
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('spg', 100)->nullable()->index()->comment('Applicable in specified selling price group only. Use of applicable_in_spg column is discontinued');
            $table->boolean('applicable_in_cg')->nullable()->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
