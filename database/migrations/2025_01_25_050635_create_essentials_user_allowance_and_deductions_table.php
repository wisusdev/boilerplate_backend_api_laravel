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
        Schema::create('essentials_user_allowance_and_deductions', function (Blueprint $table) {
            $table->integer('user_id')->index();
            $table->integer('allowance_deduction_id')->index('allow_deduct_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_user_allowance_and_deductions');
    }
};
