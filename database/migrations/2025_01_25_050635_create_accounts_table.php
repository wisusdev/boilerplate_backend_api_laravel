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
        Schema::create('accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->index();
            $table->string('name', 191);
            $table->string('account_number', 191);
            $table->text('account_details')->nullable();
            $table->integer('account_type_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->integer('created_by')->index();
            $table->boolean('is_closed')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
