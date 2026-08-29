<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Tour al que se imputa el gasto. NULL = gasto general (no específico de un tour).
            $table->unsignedBigInteger('tour_id')->nullable();
            $table->unsignedBigInteger('expense_category_id');
            // Guía (usuario con rol 'guia') al que se atribuye el gasto. Opcional.
            // Los usuarios usan UUID como clave primaria.
            $table->uuid('guide_id')->nullable();
            // Usuario que registró el gasto.
            $table->uuid('user_id')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('comment')->nullable();
            $table->date('spent_at');
            $table->string('currency_code', 3)->default('USD');

            $table->timestamps();

            $table->foreign('tour_id')->references('id')->on('tours')->nullOnDelete();
            // RESTRICT, no CASCADE: una cascada aquí borraba los gastos por SQL sin
            // eventos de Eloquent y dejaba las filas de `media` y los ficheros de
            // los recibos huérfanos en disco.
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->restrictOnDelete();
            $table->foreign('guide_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('tour_id');
            $table->index('expense_category_id');
            $table->index('guide_id');
            $table->index('spent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
