<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobante de Crédito Fiscal (CCF, tipo 03): la factura a un contribuyente.
 *
 * El receptor de un CCF va identificado por completo y en códigos de catálogo
 * (NIT, NRC, actividad CAT-019, dirección CAT-012/013/008). `dte_type` pasa a
 * ser también el tipo elegido al crear la factura: 01 Factura, 03 CCF.
 * `receptor_agente_retencion`: el cliente es gran contribuyente designado
 * agente de retención (retiene el 1 % de IVA en lugar de pagar percepción).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('receptor_nrc', 8)->nullable()->after('receptor_document_type');
            $table->string('receptor_cod_actividad', 6)->nullable()->after('receptor_nrc');
            $table->string('receptor_nombre_comercial', 150)->nullable()->after('receptor_cod_actividad');
            $table->string('receptor_departamento', 2)->nullable()->after('receptor_nombre_comercial');
            $table->string('receptor_municipio', 2)->nullable()->after('receptor_departamento');
            $table->string('receptor_distrito', 2)->nullable()->after('receptor_municipio');
            $table->string('receptor_direccion', 200)->nullable()->after('receptor_distrito');
            $table->string('receptor_telefono', 30)->nullable()->after('receptor_direccion');
            $table->boolean('receptor_agente_retencion')->default(false)->after('receptor_telefono');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'receptor_nrc', 'receptor_cod_actividad', 'receptor_nombre_comercial',
                'receptor_departamento', 'receptor_municipio', 'receptor_distrito',
                'receptor_direccion', 'receptor_telefono', 'receptor_agente_retencion',
            ]);
        });
    }
};
