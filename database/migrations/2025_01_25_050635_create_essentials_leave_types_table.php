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
        Schema::create('essentials_leave_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('leave_type', 191);
            $table->integer('max_leave_count')->nullable();
            $table->enum('leave_count_interval', ['month', 'year'])->nullable();
            $table->integer('business_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_leave_types');
    }
};
