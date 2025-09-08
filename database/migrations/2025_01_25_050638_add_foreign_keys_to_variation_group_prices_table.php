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
        Schema::table('variation_group_prices', function (Blueprint $table) {
            $table->foreign(['price_group_id'])->references(['id'])->on('selling_price_groups')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['variation_id'])->references(['id'])->on('variations')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variation_group_prices', function (Blueprint $table) {
            $table->dropForeign('variation_group_prices_price_group_id_foreign');
            $table->dropForeign('variation_group_prices_variation_id_foreign');
        });
    }
};
