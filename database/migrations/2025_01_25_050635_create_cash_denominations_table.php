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
        Schema::create('cash_denominations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id');
            $table->decimal('amount', 22, 4);
            $table->integer('total_count');
            $table->string('model_type', 191);
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_denominations');
    }
};
