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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
			$table->foreignUuid('user_id')->constrained()->onDelete('cascade');
			$table->foreignUuid('created_by')->index();
			$table->string('invoice_number')->unique();
			$table->date('invoice_date');
			$table->date('due_date')->nullable();
			$table->decimal('total_amount', 10, 2);
			$table->enum('status', ['paid', 'unpaid', 'partial', 'canceled'])->default('unpaid');
			$table->string('payment_method')->nullable();
			$table->boolean('send_email')->default(false);
			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
