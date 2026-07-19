<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Opciones de vehículo de paga para el tour (además de la gratuita implícita).
            // Estructura: [{name, surcharge}] (hasta 3).
            $table->json('vehicle_options')->nullable()->after('pricing_tiers');

            // Visibilidad de las secciones del flujo de reserva.
            // Estructura: {vehicle, pickup, coupon, fare} (bool). Ausente = todas visibles.
            $table->json('booking_sections')->nullable()->after('vehicle_options');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['vehicle_options', 'booking_sections']);
        });
    }
};
