<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Precios escalonados por nº de pasajeros: descuento porcentual por tramo.
            // Estructura: [{min_pax:int, discount_percent:float}]. Se aplica el tramo
            // con mayor min_pax <= pax. No altera el precio base almacenado.
            $table->json('pricing_tiers')->nullable()->after('child_price');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('pricing_tiers');
        });
    }
};
