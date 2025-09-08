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
        Schema::table('group_sub_taxes', function (Blueprint $table) {
            $table->foreign(['group_tax_id'])->references(['id'])->on('tax_rates')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['tax_id'])->references(['id'])->on('tax_rates')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_sub_taxes', function (Blueprint $table) {
            $table->dropForeign('group_sub_taxes_group_tax_id_foreign');
            $table->dropForeign('group_sub_taxes_tax_id_foreign');
        });
    }
};
