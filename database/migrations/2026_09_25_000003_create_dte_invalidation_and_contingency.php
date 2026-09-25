<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Facturación electrónica, fase 3: invalidación y contingencia.
 *
 * - `dte_invalidaciones`: un DTE con sello solo deja de valer con un evento de
 *   invalidación sellado por el MH (Manual Funcional v2, VII).
 * - `dte_contingencias`: periodos en que el MH no respondió. Los DTE se emiten
 *   en modelo diferido y, cuando el MH vuelve, se reportan con un evento y se
 *   transmiten en lote (Manual Funcional v2, VIII).
 * - `dte_documents.contingencia_id` y la versión enviada en línea: un documento
 *   que pasó a contingencia tras enviarse pudo haber llegado; si el MH lo
 *   tiene, esa versión es la que vale y se restaura.
 * - Permiso `invoices:invalidate-dte`, para los roles que ya emiten DTE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_contingencias', function (Blueprint $table) {
            $table->id();
            $table->string('ambiente', 2);
            $table->unsignedTinyInteger('tipo_contingencia'); // CAT-005
            $table->string('motivo', 500)->nullable();
            // open → closed → event_sent → lote_sent → done; event_rejected
            // vuelve a closed al rearmarse.
            $table->string('estado', 20)->default('open');
            $table->timestamp('inicio');
            $table->timestamp('fin')->nullable();
            // Evento de contingencia (esquema v4)
            $table->string('codigo_generacion', 36)->nullable()->unique();
            $table->longText('json_content')->nullable();
            $table->longText('firma_electronica')->nullable();
            $table->string('sello_recibido', 100)->nullable();
            $table->timestamp('evento_sellado_at')->nullable();
            // Lote
            $table->string('codigo_lote', 100)->nullable();
            $table->timestamp('lote_enviado_at')->nullable();
            $table->json('mh_response')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamps();

            $table->index(['ambiente', 'estado']);
        });

        Schema::table('dte_documents', function (Blueprint $table) {
            $table->foreignId('contingencia_id')->nullable()->after('invoice_id')
                ->constrained('dte_contingencias')->nullOnDelete();
            $table->longText('json_en_linea')->nullable()->after('firma_electronica');
            $table->longText('firma_en_linea')->nullable()->after('json_en_linea');
        });

        Schema::create('dte_invalidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dte_document_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('tipo_anulacion'); // CAT-024
            $table->string('motivo', 200)->nullable();
            $table->string('codigo_generacion', 36)->unique();
            $table->string('codigo_generacion_r', 36)->nullable();
            $table->string('solicita_nombre', 100);
            // pending → transmitted | rejected
            $table->string('estado', 20)->default('pending');
            $table->longText('json_content');
            $table->longText('firma_electronica');
            $table->string('sello_recibido', 100)->nullable();
            $table->json('mh_response')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamp('transmitido_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dte_document_id', 'estado']);
            $table->index(['estado', 'updated_at']);
        });

        $this->grantInvalidatePermission();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'invoices:invalidate-dte')->where('guard_name', 'api')->delete();

        Schema::dropIfExists('dte_invalidaciones');
        Schema::table('dte_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contingencia_id');
            $table->dropColumn(['json_en_linea', 'firma_en_linea']);
        });
        Schema::dropIfExists('dte_contingencias');
    }

    /**
     * Una instalación existente ya sembró sus permisos: el nuevo se crea aquí
     * y se asigna a los roles que ya podían emitir DTE.
     */
    private function grantInvalidatePermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $emitir = DB::table('permissions')->where('name', 'invoices:generate-dte')->where('guard_name', 'api')->value('uuid');
        if (! $emitir) {
            return; // Instalación nueva: lo siembra PermissionSeeder.
        }

        $uuid = DB::table('permissions')->where('name', 'invoices:invalidate-dte')->where('guard_name', 'api')->value('uuid');
        if (! $uuid) {
            $uuid = (string) Str::uuid();
            DB::table('permissions')->insert([
                'uuid' => $uuid, 'name' => 'invoices:invalidate-dte', 'guard_name' => 'api',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $roles = DB::table('role_has_permissions')->where('permission_id', $emitir)->pluck('role_id');
        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $uuid, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
