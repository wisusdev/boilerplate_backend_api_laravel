<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Upgrade de vehículo elegido para un tour en grupo (opcional).
            // Se guarda el vehículo y un snapshot del cargo cobrado para que el
            // total quede reproducible aunque cambie la tarifa del vehículo.
            $table->unsignedBigInteger('upgrade_vehicle_id')->nullable()->after('bookable_id');
            $table->decimal('upgrade_surcharge', 12, 2)->nullable()->after('service_fees');

            $table->foreign('upgrade_vehicle_id')
                ->references('id')->on('transport_vehicles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['upgrade_vehicle_id']);
            $table->dropColumn(['upgrade_vehicle_id', 'upgrade_surcharge']);
        });
    }
};
