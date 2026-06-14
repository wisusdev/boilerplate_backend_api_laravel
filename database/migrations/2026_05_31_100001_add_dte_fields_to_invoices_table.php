<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // DTE document identification
            $table->string('dte_type', 5)->nullable()->after('dte_code');        // '03' = CF, '01' = CCF
            $table->string('dte_number', 40)->nullable()->after('dte_type');     // Sequential: DTE-03-M001P001-000000000001
            $table->string('dte_generation_code', 36)->nullable()->unique()->after('dte_number'); // UUID
            $table->string('dte_seal', 500)->nullable()->after('dte_generation_code'); // Sello de recepción MH

            // DTE status lifecycle
            $table->string('dte_status', 20)->default('not_generated')->after('dte_seal');
            // not_generated | generating | signed | sent | accepted | rejected | error

            // Client data at the time of emission (snapshot)
            $table->string('receptor_name', 250)->nullable()->after('dte_status');
            $table->string('receptor_document', 50)->nullable()->after('receptor_name'); // NIT/DUI
            $table->string('receptor_email', 150)->nullable()->after('receptor_document');

            // Full document storage
            $table->json('dte_json')->nullable()->after('receptor_email');       // Generated DTE document
            $table->json('mh_response')->nullable()->after('dte_json');         // Raw MH API response

            // Environment and timestamps
            $table->string('dte_environment', 10)->nullable()->after('mh_response'); // '00' test / '01' prod
            $table->timestamp('dte_submitted_at')->nullable()->after('dte_environment');
            $table->timestamp('dte_accepted_at')->nullable()->after('dte_submitted_at');

            $table->index('dte_status');
            $table->index('dte_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'dte_type', 'dte_number', 'dte_generation_code', 'dte_seal',
                'dte_status', 'receptor_name', 'receptor_document', 'receptor_email',
                'dte_json', 'mh_response', 'dte_environment',
                'dte_submitted_at', 'dte_accepted_at',
            ]);
        });
    }
};
