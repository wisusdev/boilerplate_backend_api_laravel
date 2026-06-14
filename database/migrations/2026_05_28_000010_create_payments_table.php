<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->string('gateway');
            $table->string('method')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->string('transaction_reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};