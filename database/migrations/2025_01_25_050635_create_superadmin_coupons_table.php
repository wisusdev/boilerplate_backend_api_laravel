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
        Schema::create('superadmin_coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('coupon_code', 191);
            $table->string('discount_type', 191);
            $table->decimal('discount');
            $table->date('expiry_date')->nullable();
            $table->string('applied_on_packages', 191)->nullable();
            $table->string('applied_on_business', 191)->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('superadmin_coupons');
    }
};
