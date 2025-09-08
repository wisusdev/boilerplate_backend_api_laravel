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
        Schema::create('essentials_kb', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id')->index();
            $table->string('title', 191);
            $table->longText('content')->nullable();
            $table->string('status', 191);
            $table->string('kb_type', 191);
            $table->unsignedBigInteger('parent_id')->nullable()->index()->comment('id from essentials_kb table');
            $table->string('share_with', 191)->nullable()->comment('public, private, only_with');
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essentials_kb');
    }
};
