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
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('account_id')->index();
            $table->enum('type', ['debit', 'credit'])->index();
            $table->enum('sub_type', ['opening_balance', 'fund_transfer', 'deposit'])->nullable()->index();
            $table->decimal('amount', 22, 4);
            $table->string('reff_no', 191)->nullable();
            $table->dateTime('operation_date')->index();
            $table->integer('created_by')->index();
            $table->integer('transaction_id')->nullable()->index();
            $table->integer('transaction_payment_id')->nullable()->index();
            $table->integer('transfer_transaction_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
