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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index('tax_rates_business_id_foreign');
            $table->unsignedInteger('created_by')->index('tax_rates_created_by_foreign');
            $table->string('name', 191);
            $table->double('amount');
            $table->boolean('is_tax_group')->default(false);
            $table->boolean('for_tax_group')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
