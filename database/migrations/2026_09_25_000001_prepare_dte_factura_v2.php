<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica: Factura de consumidor final v2.
 *
 * - `dte_sequences`: el número de control es un correlativo por tipo de DTE que
 *   no se repite, no el id de la factura. Va por ambiente para que las pruebas
 *   en apitest no gasten números de producción.
 * - `invoices.receptor_document_type`: tipo de documento del receptor (CAT-022);
 *   antes se declaraba siempre como DUI.
 * - `invoices.dte_jws`: el documento firmado tal como se envió. Reenviar un DTE
 *   sin respuesta tiene que mandar el mismo documento, no uno nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('ambiente', 2);
            $table->string('tipo_dte', 2);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['ambiente', 'tipo_dte']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('receptor_document_type', 2)->nullable()->after('receptor_document');
            $table->longText('dte_jws')->nullable()->after('dte_json');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['receptor_document_type', 'dte_jws']);
        });

        Schema::dropIfExists('dte_sequences');
    }
};
