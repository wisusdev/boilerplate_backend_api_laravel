<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('vehicle_type');
            $table->text('description')->nullable();
            $table->string('location');

            // Tarifas (con precio de oferta opcional)
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->decimal('sale_hourly_rate', 12, 2)->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->decimal('sale_daily_rate', 12, 2)->nullable();
            // Cargo adicional fijo cuando el vehículo se ofrece como upgrade de un tour.
            $table->decimal('upgrade_surcharge', 12, 2)->nullable();

            $table->unsignedInteger('capacity');
            $table->string('currency_code', 3)->default('USD');
            $table->json('features')->nullable();

            // SEO (opcional por producto)
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_vehicles');
    }
};
