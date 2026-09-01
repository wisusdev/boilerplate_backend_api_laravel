<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro de auditoría para los cobros que se dan por buenos a mano.
 *
 * No es específico de los enlaces BAC: el efectivo y la transferencia ya se
 * marcaban como cobrados sin dejar constancia de quién lo hizo ni contra qué
 * evidencia. Con un cobro que el sistema no puede verificar por sí mismo, eso
 * deja de ser aceptable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('confirmed_by')->nullable()->after('paid_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            $table->string('confirmation_note', 255)->nullable()->after('confirmed_at');

            // Reverso: una confirmación equivocada o un contracargo se anulan,
            // nunca se borran. El histórico se conserva por auditoría.
            $table->timestamp('voided_at')->nullable()->after('confirmation_note');
            $table->foreignUuid('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['confirmed_at', 'confirmation_note', 'voided_at', 'void_reason']);
        });
    }
};
