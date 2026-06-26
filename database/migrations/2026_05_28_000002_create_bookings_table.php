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
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('party_size')->default(1);
            $table->decimal('total_price', 12, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
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
