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
        Schema::create('menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('menu_id', 36)->collation('utf8mb4_unicode_ci');
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('type')->default('custom'); // custom, page, post, category, external
            $table->string('target_type')->nullable();
            $table->char('target_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->char('parent_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->integer('order')->default(0);
            $table->string('icon')->nullable();
            $table->string('css_class')->nullable();
            $table->string('target_attr')->default('_self'); // _self, _blank
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('menu_items')->onDelete('cascade');

            $table->index(['menu_id', 'parent_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
