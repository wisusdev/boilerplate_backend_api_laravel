<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_vehicles', function (Blueprint $table) {
            // Cargo adicional fijo cuando el vehículo se ofrece como upgrade de un
            // tour en grupo. Null = el vehículo no se ofrece como upgrade.
            $table->decimal('upgrade_surcharge', 12, 2)->nullable()->after('sale_daily_rate');
        });
    }

    public function down(): void
    {
        Schema::table('transport_vehicles', function (Blueprint $table) {
            $table->dropColumn('upgrade_surcharge');
        });
    }
};
