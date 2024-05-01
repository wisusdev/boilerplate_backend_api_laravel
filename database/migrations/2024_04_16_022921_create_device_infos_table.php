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
        Schema::create('device_infos', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->string('session_token');
            $table->timestamp('login_at');
            $table->string('browser');
            $table->string('os');
            $table->string('ip');
            $table->string('country');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_infos');
    }
};
