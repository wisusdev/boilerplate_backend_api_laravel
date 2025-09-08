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
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->unsignedInteger('business_id')->index('categories_business_id_foreign');
            $table->unsignedInteger('parent_id')->nullable()->index('categories_parent_id_foreign')->comment('Parent category ID, if any');
            $table->boolean('is_active')->default(true);
            $table->string('short_code', 191)->nullable();
            $table->uuid('created_by')->index('categories_created_by_foreign');
            $table->string('category_type', 191)->nullable();
            $table->text('description')->nullable();
            $table->string('slug', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
