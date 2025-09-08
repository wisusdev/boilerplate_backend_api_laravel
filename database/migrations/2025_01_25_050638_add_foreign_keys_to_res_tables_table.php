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
        Schema::table('res_tables', function (Blueprint $table) {
            $table->foreign(['business_id'])->references(['id'])->on('businesses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('res_tables', function (Blueprint $table) {
            $table->dropForeign('res_tables_business_id_foreign');
        });
    }
};
