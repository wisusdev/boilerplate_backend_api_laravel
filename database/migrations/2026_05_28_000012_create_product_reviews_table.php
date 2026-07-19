<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reviewable'); // reviewable_id + reviewable_type
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            // Una reseña por usuario y producto.
            $table->unique(['user_id', 'reviewable_id', 'reviewable_type'], 'product_reviews_user_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
