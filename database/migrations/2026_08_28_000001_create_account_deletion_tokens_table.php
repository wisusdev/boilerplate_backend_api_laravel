<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los tokens de borrado de cuenta vivían en `password_reset_tokens`, así que un
 * token de restablecimiento servía para borrar la cuenta y viceversa. Cada
 * propósito necesita su propia tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_tokens');
    }
};
