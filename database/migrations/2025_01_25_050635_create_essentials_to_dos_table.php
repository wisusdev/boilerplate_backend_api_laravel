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
        Schema::create('essentials_to_dos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->index();
            $table->text('task');
            $table->dateTime('date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('task_id', 191)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status', 191)->nullable()->index();
            $table->string('estimated_hours', 191)->nullable();
            $table->string('priority', 191)->nullable()->index();
            $table->integer('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_to_dos');
    }
};
