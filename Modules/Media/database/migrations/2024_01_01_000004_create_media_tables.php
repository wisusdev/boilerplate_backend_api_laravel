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
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('filename');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->char('uploaded_by', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->string('folder')->default('/');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');

            $table->index('mime_type');
            $table->index('folder');
        });

        // Media folders for organization
        Schema::create('media_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->char('parent_id', 36)->nullable()->collation('utf8mb4_unicode_ci');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('media_folders')->onDelete('cascade');

            $table->unique(['slug', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_folders');
        Schema::dropIfExists('media');
    }
};
