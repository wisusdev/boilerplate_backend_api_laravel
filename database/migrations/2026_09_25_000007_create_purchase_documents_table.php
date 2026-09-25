<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos que vamosPues emite al COMPRAR:
 *
 *  - Factura de sujeto excluido (14): a quien no es contribuyente del IVA
 *    (guías, transportistas, proveedores informales). Puede retener el 10 %
 *    de renta a una persona natural que presta un servicio.
 *  - Comprobante de retención (07): como agente de retención designado, a un
 *    proveedor contribuyente al que se retiene IVA (CAT-006: 1 %, 13 %, otros)
 *    sobre los documentos que él emitió.
 *
 * El proveedor y las líneas se guardan como se declararon; el enlace al gasto
 * es opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_documents', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 5);                     // fse | cr
            $table->string('number', 20);                  // FSE-00001 / CR-00001
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();

            // Proveedor (receptor del DTE)
            $table->string('proveedor_nombre', 250);
            $table->string('proveedor_tipo_documento', 2); // CAT-022
            $table->string('proveedor_num_documento', 20);
            $table->string('proveedor_nrc', 8)->nullable();
            $table->string('proveedor_cod_actividad', 6)->nullable();
            $table->string('proveedor_nombre_comercial', 150)->nullable();
            $table->string('proveedor_departamento', 2);
            $table->string('proveedor_municipio', 2);
            $table->string('proveedor_distrito', 2);
            $table->string('proveedor_direccion', 200);
            $table->string('proveedor_telefono', 30)->nullable();
            $table->string('proveedor_correo', 100)->nullable();

            // Sujeto excluido: retención de renta, condición y forma de pago.
            $table->boolean('retener_renta')->default(false);
            $table->unsignedTinyInteger('condicion_operacion')->default(1); // CAT-016
            $table->string('forma_pago', 2)->nullable();                     // CAT-017

            $table->json('items');
            $table->decimal('total', 12, 2);              // FSE: a pagar; CR: IVA retenido
            $table->string('observaciones', 3000)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Resumen del último DTE (mismas columnas que la factura).
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
            $table->index(['kind', 'dte_status']);
        });

        Schema::table('dte_documents', function (Blueprint $table) {
            $table->foreignId('purchase_document_id')->nullable()->after('credit_note_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_document_id');
        });
        Schema::dropIfExists('purchase_documents');
    }
};
