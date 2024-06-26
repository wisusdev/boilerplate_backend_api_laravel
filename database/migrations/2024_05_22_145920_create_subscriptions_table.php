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
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('package_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('trial_ends_at')->nullable();
            $table->decimal('package_price', 8, 2);
            $table->text('package_details');
            $table->uuid('created_by')->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_transaction_id')->nullable();
            $table->enum('status', ['approved', 'waiting', 'declined', 'cancel'])->default('waiting');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscriptions');
    }
};
