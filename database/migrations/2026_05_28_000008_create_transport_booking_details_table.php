<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_booking_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->string('rental_type', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_booking_details');
    }
};
