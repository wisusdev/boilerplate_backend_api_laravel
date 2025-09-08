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
        Schema::create('activity_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('log_name', 191)->nullable()->index();
            $table->text('description');
            $table->integer('subject_id')->nullable();
            $table->string('subject_type', 191)->nullable();
            $table->string('event', 191)->nullable();
            $table->integer('business_id')->nullable();
            $table->integer('causer_id')->nullable();
            $table->string('causer_type', 191)->nullable();
            $table->text('properties')->nullable();
            $table->char('batch_uuid', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
