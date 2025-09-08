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
        Schema::table('variation_value_templates', function (Blueprint $table) {
            $table->foreign(['variation_template_id'])->references(['id'])->on('variation_templates')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variation_value_templates', function (Blueprint $table) {
            $table->dropForeign('variation_value_templates_variation_template_id_foreign');
        });
    }
};
