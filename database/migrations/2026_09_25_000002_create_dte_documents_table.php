<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada DTE emitido en su propia fila.
 *
 * Una factura puede tener varios documentos a lo largo de su vida (uno
 * rechazado y el que lo sustituye; más adelante, invalidaciones y notas).
 * El documento es la fuente de verdad: guarda el JSON exacto que se firmó y
 * el JWS que se envía, el estado frente al MH y cada intento. Las columnas
 * `dte_*` de la factura quedan como resumen del último documento para los
 * listados y filtros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('tipo_dte', 2);
            $table->string('ambiente', 2);
            $table->unsignedTinyInteger('version');
            $table->string('numero_control', 31);
            $table->string('codigo_generacion', 36)->unique();
            // pending: firmado, sin sello (sin respuesta o envío interrumpido)
            // transmitted: con sello, es un DTE válido
            // rejected: el MH lo rechazó; nunca tuvo validez
            $table->string('estado', 20)->default('pending');
            // El JSON exacto que se firmó: nunca se vuelve a serializar.
            $table->longText('json_content');
            $table->longText('firma_electronica');
            $table->string('sello_recibido', 100)->nullable();
            $table->string('fh_procesamiento', 30)->nullable();
            $table->json('mh_response')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamp('transmitido_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'estado']);
            $table->index(['estado', 'updated_at']);
            $table->unique(['ambiente', 'tipo_dte', 'numero_control']);
        });

        // Documentos emitidos con la fase 1, que vivían en la propia factura.
        $estados = [
            'accepted' => 'transmitted',
            'rejected' => 'rejected',
            'error' => 'pending',
            'generating' => 'pending',
        ];
        DB::table('invoices')
            ->whereNotNull('dte_jws')
            ->whereNotNull('dte_generation_code')
            ->orderBy('id')
            ->each(function ($inv) use ($estados) {
                // El JSON firmado es el payload del JWS, byte a byte.
                $payload = explode('.', $inv->dte_jws)[1] ?? '';
                $exacto = base64_decode(strtr($payload, '-_', '+/'));
                $json = json_decode($exacto ?: '{}', true) ?? [];
                DB::table('dte_documents')->insert([
                    'invoice_id' => $inv->id,
                    'tipo_dte' => $inv->dte_type ?? '01',
                    'ambiente' => $inv->dte_environment ?? '00',
                    'version' => $json['identificacion']['version'] ?? 2,
                    'numero_control' => $inv->dte_number,
                    'codigo_generacion' => $inv->dte_generation_code,
                    'estado' => $estados[$inv->dte_status] ?? 'pending',
                    'json_content' => $exacto,
                    'firma_electronica' => $inv->dte_jws,
                    'sello_recibido' => $inv->dte_seal,
                    'mh_response' => $inv->mh_response,
                    'intentos' => $inv->dte_submitted_at ? 1 : 0,
                    'transmitido_at' => $inv->dte_accepted_at,
                    'created_at' => $inv->updated_at,
                    'updated_at' => $inv->updated_at,
                ]);
            });

        DB::table('invoices')->whereIn('dte_status', ['error', 'generating'])->whereNotNull('dte_jws')
            ->update(['dte_status' => 'pending']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['dte_json', 'dte_jws']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('dte_json')->nullable();
            $table->longText('dte_jws')->nullable();
        });

        DB::table('dte_documents')->orderBy('id')->each(function ($doc) {
            DB::table('invoices')->where('id', $doc->invoice_id)->update([
                'dte_json' => $doc->json_content,
                'dte_jws' => $doc->firma_electronica,
            ]);
        });
        DB::table('invoices')->where('dte_status', 'pending')->update(['dte_status' => 'error']);

        Schema::dropIfExists('dte_documents');
    }
};
