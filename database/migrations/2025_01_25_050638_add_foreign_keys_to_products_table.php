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
        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['business_id'])->references(['id'])->on('businesses')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['brand_id'])->references(['id'])->on('brands')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['category_id'])->references(['id'])->on('categories')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['created_by'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['sub_category_id'])->references(['id'])->on('categories')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['tax'])->references(['id'])->on('tax_rates')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['unit_id'])->references(['id'])->on('units')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_brand_id_foreign');
            $table->dropForeign('products_business_id_foreign');
            $table->dropForeign('products_category_id_foreign');
            $table->dropForeign('products_created_by_foreign');
            $table->dropForeign('products_sub_category_id_foreign');
            $table->dropForeign('products_tax_foreign');
            $table->dropForeign('products_unit_id_foreign');
        });
    }
};
