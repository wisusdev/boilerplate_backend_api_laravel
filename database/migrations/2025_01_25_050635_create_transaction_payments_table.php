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
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->nullable()->index('transaction_payments_transaction_id_foreign');
            $table->integer('business_id')->nullable();
            $table->boolean('is_return')->default(false)->comment('Used during sales to return the change');
            $table->decimal('amount', 22, 4)->default(0);
            $table->string('method', 191)->nullable();
            $table->string('transaction_no', 191)->nullable();
            $table->string('payment_type', 191)->nullable()->index();
            $table->string('card_transaction_number', 191)->nullable();
            $table->string('card_number', 191)->nullable();
            $table->string('card_type', 191)->nullable();
            $table->string('card_holder_name', 191)->nullable();
            $table->string('card_month', 191)->nullable();
            $table->string('card_year', 191)->nullable();
            $table->string('card_security', 5)->nullable();
            $table->string('cheque_number', 191)->nullable();
            $table->string('bank_account_number', 191)->nullable();
            $table->dateTime('paid_on')->nullable();
            $table->integer('created_by')->nullable()->index();
            $table->boolean('paid_through_link')->default(false);
            $table->string('gateway', 191)->nullable();
            $table->boolean('is_advance')->default(false);
            $table->integer('payment_for')->nullable()->comment('stores the contact id');
            $table->integer('parent_id')->nullable()->index();
            $table->string('note', 191)->nullable();
            $table->string('document', 191)->nullable();
            $table->string('payment_ref_no', 191)->nullable();
            $table->integer('account_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
    }
};
