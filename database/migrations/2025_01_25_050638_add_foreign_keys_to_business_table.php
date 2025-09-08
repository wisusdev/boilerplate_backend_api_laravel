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
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreign(['currency_id'])->references(['id'])->on('currencies')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['default_sales_tax'])->references(['id'])->on('tax_rates')->onUpdate('no action')->onDelete('no action');
            $table->foreign('owner_id')->references('id')->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign('businesses_currency_id_foreign');
            $table->dropForeign('businesses_default_sales_tax_foreign');
            $table->dropForeign('businesses_owner_id_foreign');
        });
    }
};
