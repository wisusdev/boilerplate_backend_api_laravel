<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete()->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('status')->default('pending');

            // ── DTE (Facturación Electrónica El Salvador) ──
            $table->string('dte_type', 5)->nullable();           // '03' = CF, '01' = CCF
            $table->string('dte_number', 40)->nullable();        // Correlativo: DTE-03-M001P001-000000000001
            $table->string('dte_generation_code', 36)->nullable()->unique(); // UUID
            $table->string('dte_seal', 500)->nullable();         // Sello de recepción MH
            $table->string('dte_status', 20)->default('not_generated'); // not_generated|generating|signed|sent|accepted|rejected|error
            // Datos del receptor al momento de la emisión (snapshot)
            $table->string('receptor_name', 250)->nullable();
            $table->string('receptor_document', 50)->nullable(); // NIT/DUI
            $table->string('receptor_email', 150)->nullable();
            // Documento completo y respuesta de MH
            $table->json('dte_json')->nullable();
            $table->json('mh_response')->nullable();
            $table->string('dte_environment', 10)->nullable();   // '00' test / '01' prod
            $table->timestamp('dte_submitted_at')->nullable();
            $table->timestamp('dte_accepted_at')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('dte_status');
            $table->index('dte_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
