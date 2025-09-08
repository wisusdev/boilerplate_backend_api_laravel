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
        Schema::create('currencies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country', 100)->comment('Nombre del país al que pertenece la moneda');
            $table->string('country_code', 10)->unique()->comment('Código único del país, por ejemplo: MX, US, ES, GB, JP');
            $table->string('currency', 100)->comment('Nombre de la moneda');
            $table->string('code', 25)->comment('Código de la moneda, por ejemplo: USD, EUR, MXN');
            $table->string('symbol', 25)->comment('Símbolo de la moneda, por ejemplo: $, €, £, ¥');
            $table->string('thousand_separator', 10)->default(',')->comment('Separador de miles, por ejemplo: , o .');
            $table->string('decimal_separator', 10)->default('.')->comment('Separador decimal, por ejemplo: . o ,');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
