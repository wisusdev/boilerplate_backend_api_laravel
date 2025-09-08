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
        Schema::create('essentials_payroll_group_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_group_id')->index('essentials_payroll_group_transactions_payroll_group_id_foreign');
            $table->integer('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_payroll_group_transactions');
    }
};
