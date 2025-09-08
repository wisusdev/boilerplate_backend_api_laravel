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
        Schema::create('packages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->text('description');
            $table->integer('location_count')->comment('No. of Business Locations, 0 = infinite option.');
            $table->integer('user_count');
            $table->integer('product_count');
            $table->boolean('bookings')->default(false)->comment('Enable/Disable bookings');
            $table->boolean('kitchen')->default(false)->comment('Enable/Disable kitchen');
            $table->boolean('order_screen')->default(false)->comment('Enable/Disable order_screen');
            $table->boolean('tables')->default(false)->comment('Enable/Disable tables');
            $table->integer('invoice_count');
            $table->enum('interval', ['days', 'months', 'years']);
            $table->integer('interval_count');
            $table->integer('trial_days');
            $table->decimal('price', 22, 4);
            $table->longText('custom_permissions');
            $table->integer('created_by');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active');
            $table->boolean('mark_package_as_popular');
            $table->longText('businesses')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_one_time')->default(false);
            $table->boolean('enable_custom_link')->default(false);
            $table->string('custom_link', 191)->nullable();
            $table->string('custom_link_text', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
