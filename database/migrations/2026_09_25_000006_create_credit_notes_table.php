<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de crédito (05) y de débito (06): ajustan a la baja o al alza un CCF
 * ya sellado (devoluciones, descuentos, cargos omitidos, correcciones de
 * precio). Una Factura de consumidor final no se ajusta con notas: se invalida.
 *
 * Un DTE pertenece ahora a una factura o a una nota: `dte_documents.invoice_id`
 * pasa a ser opcional y se añade `credit_note_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);                 // credit | debit
            $table->string('number', 20);               // NC-00001 / ND-00001
            $table->string('motivo', 500);
            // Importes sin IVA, como el CCF que ajustan.
            $table->decimal('subtotal', 12, 2);
            $table->decimal('iva', 12, 2);
            $table->decimal('total', 12, 2);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Resumen del último DTE de la nota (mismas columnas que en la factura).
            $table->string('dte_type', 5)->nullable();
            $table->string('dte_status', 20)->default('not_generated');
            $table->string('dte_number', 40)->nullable();
            $table->string('dte_generation_code', 36)->nullable();
            $table->string('dte_seal', 500)->nullable();
            $table->string('dte_environment', 10)->nullable();
            $table->timestamp('dte_submitted_at')->nullable();
            $table->timestamp('dte_accepted_at')->nullable();
            $table->json('mh_response')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'number']);
            $table->index(['invoice_id', 'kind']);
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->string('description', 250);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);      // sin IVA
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('dte_documents', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
            });
        }
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
        });
        Schema::table('dte_documents', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            }
            $table->foreignId('credit_note_id')->nullable()->after('invoice_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_note_id');
        });
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
    }
};
