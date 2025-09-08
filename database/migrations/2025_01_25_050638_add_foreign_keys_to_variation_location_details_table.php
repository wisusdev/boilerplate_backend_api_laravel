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
        Schema::table('variation_location_details', function (Blueprint $table) {
            $table->foreign(['location_id'])->references(['id'])->on('business_locations')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['variation_id'])->references(['id'])->on('variations')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variation_location_details', function (Blueprint $table) {
            $table->dropForeign('variation_location_details_location_id_foreign');
            $table->dropForeign('variation_location_details_variation_id_foreign');
        });
    }
};
