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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('subscriptions_business_id_foreign');
            $table->unsignedInteger('package_id')->index();
            $table->date('start_date')->nullable();
            $table->date('trial_end_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('package_price', 22, 4);
            $table->decimal('original_price', 22, 4)->nullable();
            $table->string('coupon_code', 191)->nullable();
            $table->longText('package_details');
            $table->unsignedInteger('created_id')->index();
            $table->string('paid_via', 191)->nullable();
            $table->string('payment_transaction_id', 191)->nullable();
            $table->enum('status', ['approved', 'waiting', 'declined'])->default('waiting');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
