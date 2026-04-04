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
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable(); // Rendered HTML from GrapesJS
            $table->json('content_json')->nullable(); // GrapesJS JSON data
            $table->json('content_css')->nullable(); // GrapesJS CSS
            $table->string('template')->default('default');
            $table->string('layout')->default('default');
            $table->enum('status', ['draft', 'published', 'scheduled', 'private'])->default('draft');
            $table->char('author_id', 36)->collation('utf8mb4_unicode_ci');
            $table->char('parent_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->integer('order')->default(0);
            $table->string('featured_image')->nullable();
            $table->json('meta')->nullable(); // SEO meta data
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('pages')->onDelete('set null');

            $table->index(['status', 'published_at']);
            $table->index('parent_id');
        });

        // Page revisions for version history
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->char('page_id', 36)->collation('utf8mb4_unicode_ci');
            $table->char('user_id', 36)->collation('utf8mb4_unicode_ci');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable();
            $table->json('content_json')->nullable();
            $table->json('content_css')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
    }
};
