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
        Schema::create('res_tables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('res_tables_business_id_foreign');
            $table->unsignedInteger('location_id');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('res_tables');
    }
};
