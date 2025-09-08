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
        Schema::create('essentials_reminders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->index();
            $table->integer('user_id')->index();
            $table->string('name', 191);
            $table->date('date');
            $table->time('time');
            $table->time('end_time')->nullable();
            $table->enum('repeat', ['one_time', 'every_day', 'every_week', 'every_month']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_reminders');
    }
};
