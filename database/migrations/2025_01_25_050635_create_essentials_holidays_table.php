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
        Schema::create('essentials_holidays', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('business_id')->index();
            $table->integer('location_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_holidays');
    }
};
