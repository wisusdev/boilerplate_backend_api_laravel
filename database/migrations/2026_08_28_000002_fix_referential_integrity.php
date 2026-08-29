<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige dos cascadas que borraban datos en silencio y dejaban huérfanos.
 *
 * 1. `expenses.expense_category_id` era CASCADE: borrar una categoría borraba
 *    los gastos por SQL, sin eventos de Eloquent, dejando las filas de `media`
 *    y los ficheros de los recibos huérfanos en disco.
 *
 * 2. `bookings.user_id` era CASCADE: borrar un usuario arrastraba su historial
 *    de reservas. Como `payments` es polimórfico y no tiene clave foránea, los
 *    pagos quedaban apuntando a reservas inexistentes. Además chocaba con el
 *    RESTRICT de `invoices.booking_id` y reventaba con un error de SQL.
 *
 * En ambos casos la relación pasa a RESTRICT: el borrado falla de forma
 * explícita en vez de destruir historial contable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite no soporta soltar y recrear claves foráneas en caliente; en los
        // tests la base se construye desde cero con el esquema ya corregido.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->foreign('expense_category_id')
                ->references('id')->on('expense_categories')
                ->restrictOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });

        // 3. `booking_messages.user_id` no tenía clave foránea: al borrar
        //    definitivamente un usuario quedaban mensajes sin autor. Se limpian
        //    los que ya estén huérfanos antes de crear la restricción.
        DB::table('booking_messages')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->update(['user_id' => null]);

        Schema::table('booking_messages', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->foreign('expense_category_id')
                ->references('id')->on('expense_categories')
                ->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('booking_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
