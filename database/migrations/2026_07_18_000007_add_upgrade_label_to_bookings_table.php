<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nombre de la opción de vehículo elegida (configurada por tour).
            // Sustituye a upgrade_vehicle_id, que queda sin uso.
            $table->string('upgrade_label')->nullable()->after('upgrade_vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('upgrade_label');
        });
    }
};
