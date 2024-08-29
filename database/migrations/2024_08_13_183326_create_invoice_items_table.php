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
        Schema::create('invoice_items', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->foreignUuid('invoice_id')->constrained()->onDelete('cascade');
			$table->string('item_id');
			$table->string('type'); // product, package
			$table->string('name');
			$table->string('description');
			$table->integer('quantity');
			$table->decimal('unit_price', 10, 2);
			$table->decimal('total_price', 10, 2);
			$table->json('metadata')->nullable();
			$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
