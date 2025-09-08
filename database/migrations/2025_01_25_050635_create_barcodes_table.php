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
        Schema::create('barcodes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->double('width')->nullable();
            $table->double('height')->nullable();
            $table->double('paper_width')->nullable();
            $table->double('paper_height')->nullable();
            $table->double('top_margin')->nullable();
            $table->double('left_margin')->nullable();
            $table->double('row_distance')->nullable();
            $table->double('col_distance')->nullable();
            $table->integer('stickers_in_one_row')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_continuous')->default(false);
            $table->integer('stickers_in_one_sheet')->nullable();
            $table->unsignedInteger('business_id')->nullable()->index('barcodes_business_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcodes');
    }
};
