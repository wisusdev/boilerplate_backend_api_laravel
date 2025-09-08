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
        Schema::create('dashboard_configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('dashboard_configurations_business_id_foreign');
            $table->integer('created_by');
            $table->string('name', 191);
            $table->string('color', 191);
            $table->text('configuration')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_configurations');
    }
};
