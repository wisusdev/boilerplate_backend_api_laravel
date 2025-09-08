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
        Schema::table('cash_register_transactions', function (Blueprint $table) {
            $table->foreign(['cash_register_id'])->references(['id'])->on('cash_registers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_register_transactions', function (Blueprint $table) {
            $table->dropForeign('cash_register_transactions_cash_register_id_foreign');
        });
    }
};
