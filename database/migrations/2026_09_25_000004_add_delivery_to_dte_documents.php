<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega del DTE al receptor (Manual Funcional v2, IV): cuándo, a quién y si
 * lo que recibió ya llevaba sello. Un DTE entregado en contingencia se vuelve a
 * enviar cuando obtiene el sello.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->timestamp('entregado_at')->nullable()->after('transmitido_at');
            $table->string('entregado_a', 150)->nullable()->after('entregado_at');
            $table->boolean('entregado_con_sello')->default(false)->after('entregado_a');
        });
    }

    public function down(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropColumn(['entregado_at', 'entregado_a', 'entregado_con_sello']);
        });
    }
};
