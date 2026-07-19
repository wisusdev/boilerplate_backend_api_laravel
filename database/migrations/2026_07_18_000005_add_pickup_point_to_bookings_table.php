<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Punto de recogida indicado por el cliente al agendar un tour:
            // dirección escrita y, opcionalmente, marcador en el mapa.
            $table->string('pickup_address', 500)->nullable()->after('notes');
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('pickup_address');
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['pickup_address', 'pickup_lat', 'pickup_lng']);
        });
    }
};
