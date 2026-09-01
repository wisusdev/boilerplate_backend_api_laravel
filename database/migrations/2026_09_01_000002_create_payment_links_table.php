<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces de pago emitidos a mano en el portal del banco.
 *
 * Va aparte de `payments` a propósito: nada de esto significa algo para Stripe
 * o PayPal, y meterlo allí llenaría la tabla de columnas nulas. El dinero sigue
 * viviendo en `payments.status`; aquí solo vive el ciclo de vida del ENLACE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();

            // Deja sitio a otro banco sin migración: la lógica es la misma.
            $table->string('provider', 20)->default('bac');

            // Referencia NUESTRA (no la del banco). Es lo que el agente teclea en
            // la descripción del enlace para poder cuadrar después.
            $table->string('reference', 32)->unique();

            $table->string('url', 500)->nullable();
            $table->string('status', 20)->default('draft');

            // Copia del importe al emitir: si la reserva cambia después, el desfase
            // se ve en vez de pasar desapercibido.
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('USD');

            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->string('sent_channel', 20)->nullable();

            // El cliente dice "ya pagué". No mueve dinero: crea trabajo con
            // evidencia adjunta, que es justo lo que faltaba.
            $table->timestamp('reported_at')->nullable();
            $table->string('reported_ref', 40)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
