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
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');

            // Precios
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('child_price', 12, 2)->nullable();

            // Duración y políticas de reserva
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('duration_nights')->nullable();
            $table->unsignedInteger('min_advance_days')->nullable();   // antelación mínima (días)
            $table->unsignedInteger('cancellation_hours')->nullable(); // cancelación gratuita hasta X h antes

            $table->unsignedInteger('max_capacity');
            $table->string('location');
            $table->foreignId('category_id')->nullable()->constrained('tour_categories')->nullOnDelete();
            $table->string('currency_code', 3)->default('USD');

            // Bloques de contenido (JSON)
            $table->json('itinerary')->nullable();
            $table->json('highlights')->nullable();
            $table->json('includes')->nullable();
            $table->json('excludes')->nullable();
            $table->json('service_fees')->nullable(); // add-ons opcionales: [{name, amount, calc}]
            $table->json('faqs')->nullable();

            // Mapa
            $table->string('map_url')->nullable();
            $table->json('map_markers')->nullable();

            // SEO (opcional por producto)
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            // Estado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
