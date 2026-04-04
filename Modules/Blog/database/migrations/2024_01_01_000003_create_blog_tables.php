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
        // Categories table (hierarchical taxonomy)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->char('parent_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->string('featured_image')->nullable();
            $table->json('meta')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('set null');
        });

        // Tags table (flat taxonomy)
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // Posts table
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable(); // Rendered HTML
            $table->enum('status', ['draft', 'published', 'scheduled', 'private'])->default('draft');
            $table->char('author_id', 36)->collation('utf8mb4_unicode_ci');
            $table->string('featured_image')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('allow_comments')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['status', 'published_at']);
            $table->index('is_featured');
        });

        // Pivot: posts_categories
        Schema::create('category_post', function (Blueprint $table) {
            $table->char('post_id', 36)->collation('utf8mb4_unicode_ci');
            $table->char('category_id', 36)->collation('utf8mb4_unicode_ci');

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');

            $table->primary(['post_id', 'category_id']);
        });

        // Pivot: posts_tags
        Schema::create('post_tag', function (Blueprint $table) {
            $table->char('post_id', 36)->collation('utf8mb4_unicode_ci');
            $table->char('tag_id', 36)->collation('utf8mb4_unicode_ci');

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            $table->primary(['post_id', 'tag_id']);
        });

        // Comments table
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('post_id', 36)->collation('utf8mb4_unicode_ci');
            $table->char('user_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->char('parent_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'spam', 'trash'])->default('pending');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');

            $table->index(['post_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
