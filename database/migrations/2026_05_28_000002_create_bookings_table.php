<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('bookable_type', 100);
            $table->unsignedBigInteger('bookable_id');

            // Upgrade de vehículo elegido para un tour en grupo (opcional). Se guarda
            // el vehículo, un snapshot del nombre y del cargo cobrado para que el total
            // quede reproducible aunque cambie la tarifa del vehículo.
            $table->unsignedBigInteger('upgrade_vehicle_id')->nullable();
            $table->string('upgrade_label')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('party_size')->default(1);
            $table->decimal('total_price', 12, 2);
            $table->json('service_fees')->nullable(); // snapshot de add-ons cobrados
            $table->decimal('upgrade_surcharge', 12, 2)->nullable();

            // Cupón aplicado (opcional) y descuento resultante (snapshot).
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();

            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();

            // Punto de recogida indicado por el cliente al agendar un tour: dirección
            // escrita y, opcionalmente, marcador en el mapa.
            $table->string('pickup_address', 500)->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('upgrade_vehicle_id')->references('id')->on('transport_vehicles')->nullOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();

            $table->index('user_id');
            $table->index('status');
            $table->index('starts_at');
            // Cubre también las búsquedas por (bookable_type, bookable_id) como prefijo.
            $table->index(['bookable_type', 'bookable_id', 'starts_at', 'ends_at'], 'bookings_bookable_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
