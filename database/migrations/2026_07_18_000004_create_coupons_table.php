<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description')->nullable();

            // Tipo de descuento: 'percentage' (0-100) o 'fixed' (monto).
            $table->string('type', 20)->default('percentage');
            $table->decimal('value', 12, 2);
            // Tope de descuento para cupones porcentuales (opcional).
            $table->decimal('max_discount', 12, 2)->nullable();

            // Condiciones de aplicación (todas opcionales).
            $table->unsignedInteger('min_pax')->nullable();
            $table->decimal('min_amount', 12, 2)->nullable();
            // Ámbito: 'all' | 'tour' | 'transport'.
            $table->string('applies_to', 20)->default('all');

            // Límites de uso.
            $table->unsignedInteger('usage_limit')->nullable();   // global; null = ilimitado
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_user_limit')->nullable(); // por usuario; null = ilimitado

            // Vigencia.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id')->nullable()->after('upgrade_surcharge');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('coupon_id');

            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'discount_amount']);
        });

        Schema::dropIfExists('coupons');
    }
};
