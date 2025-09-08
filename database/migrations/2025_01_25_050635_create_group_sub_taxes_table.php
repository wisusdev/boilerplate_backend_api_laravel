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
        Schema::create('group_sub_taxes', function (Blueprint $table) {
            $table->unsignedInteger('group_tax_id')->index('group_sub_taxes_group_tax_id_foreign');
            $table->unsignedInteger('tax_id')->index('group_sub_taxes_tax_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_sub_taxes');
    }
};
