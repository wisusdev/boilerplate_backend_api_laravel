<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constancia de aceptación de términos y políticas: cuándo y qué versión
 * (LegalDocuments::version()) aceptó cada cliente al registrarse y al reservar.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'bookings'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->timestamp('terms_accepted_at')->nullable();
                $table->string('terms_version', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'bookings'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['terms_accepted_at', 'terms_version']);
            });
        }
    }
};
