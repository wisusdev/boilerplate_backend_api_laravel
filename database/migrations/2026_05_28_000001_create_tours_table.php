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
            $table->text('description');
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('max_capacity');
            $table->string('location');
            $table->foreignId('category_id')->nullable()->constrained('tour_categories')->nullOnDelete();
            $table->string('currency_code', 3)->default('USD');
            $table->json('itinerary')->nullable();
            $table->json('highlights')->nullable();
            $table->string('map_url')->nullable();
            $table->json('map_markers')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('is_active')->default(true);
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