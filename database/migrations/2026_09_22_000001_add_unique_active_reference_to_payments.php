<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PaymentLinkService::confirm() ya rechazaba una autorización bancaria
 * repetida con una consulta de aplicación (exists()), pero esa consulta no
 * bloquea nada: dos confirmaciones concurrentes sobre dos PaymentLink
 * distintos, con la misma autorización, podían leer "no repetida" las dos
 * antes de que ninguna terminara de escribir, y ambas acababan marcando
 * pagos distintos como pagados con la misma autorización (condición de
 * carrera). Esto lo cierra a nivel de base de datos, que es el único sitio
 * que puede arbitrar de verdad entre dos transacciones concurrentes.
 *
 * MySQL no soporta índices únicos parciales (con WHERE); se usa una columna
 * generada que vale NULL en cuanto el pago se anula, y el índice único va
 * sobre esa columna — los NULL nunca chocan entre sí, así que un pago
 * anulado deja de contar para la unicidad, igual que hacía el
 * `whereNull('voided_at')` de la consulta de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_reference_active')->nullable()->virtualAs(
                'CASE WHEN voided_at IS NULL THEN transaction_reference ELSE NULL END'
            );
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['gateway', 'transaction_reference_active'], 'payments_gateway_active_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_gateway_active_reference_unique');
            $table->dropColumn('transaction_reference_active');
        });
    }
};
