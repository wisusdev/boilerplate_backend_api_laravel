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
        Schema::create('bookings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('contact_id')->index('bookings_contact_id_foreign');
            $table->unsignedInteger('waiter_id')->nullable()->index();
            $table->unsignedInteger('table_id')->nullable()->index();
            $table->integer('correspondent_id')->nullable()->index();
            $table->unsignedInteger('business_id')->index('bookings_business_id_foreign');
            $table->unsignedInteger('location_id')->index();
            $table->dateTime('booking_start');
            $table->dateTime('booking_end');
            $table->uuid('created_by')->index('bookings_created_by_foreign');
            $table->string('booking_status', 191)->index();
            $table->text('booking_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
