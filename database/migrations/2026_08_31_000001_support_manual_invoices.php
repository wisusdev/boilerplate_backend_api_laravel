<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación manual.
 *
 * Hasta ahora una factura solo podía nacer de una reserva confirmada
 * (`booking_id` obligatorio y único), así que el back-office no tenía forma de
 * emitir una a mano. Ahora la reserva es opcional y los conceptos viven en su
 * propia tabla, de modo que una factura puede combinar tours, vehículos y
 * líneas libres.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: una instalación nueva ya crea la columna en la migración
        // base; esta migración solo pone al día las bases existentes.
        if (! Schema::hasColumn('invoices', 'notes')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('receptor_email');
            });
        }

        // `booking_id` pasa a ser opcional. El índice único se mantiene: MySQL y
        // SQLite admiten varios NULL, así que una reserva sigue teniendo como
        // mucho una factura y las manuales no chocan entre sí.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['booking_id']);
            });

            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('booking_id')->nullable()->change();
                $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            });
        }

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Snapshot: la descripción y el precio quedan fijados al emitir, aunque
            // después cambie el catálogo.
            $table->string('description', 250);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Referencia opcional al catálogo, solo informativa.
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transport_vehicle_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');

        if (Schema::hasColumn('invoices', 'notes')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
