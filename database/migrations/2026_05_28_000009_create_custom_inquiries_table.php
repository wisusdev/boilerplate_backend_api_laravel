<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable()->index();
            // Datos de contacto para consultas de invitados (sin cuenta).
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->json('preferred_destinations');
            $table->date('travel_start_date')->nullable();
            $table->date('travel_end_date')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->unsignedInteger('travelers_count')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_inquiries');
    }
};