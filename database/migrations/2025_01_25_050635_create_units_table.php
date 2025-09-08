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
        Schema::create('units', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('units_business_id_foreign');
            $table->uuid('created_by')->index('units_created_by_foreign');
            $table->boolean('is_active')->default(true);
            $table->string('actual_name', 191);
            $table->string('short_name', 191);
            $table->boolean('allow_decimal');
            $table->integer('base_unit_id')->nullable()->index();
            $table->decimal('base_unit_multiplier', 20, 4)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
