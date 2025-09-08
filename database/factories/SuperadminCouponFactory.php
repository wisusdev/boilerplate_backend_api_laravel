<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class SuperadminCouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_code' => fake()->text(191),
            'discount_type' => fake()->text(191),
            'discount' => fake()->randomFloat(2, 0, 999999),
            'expiry_date' => fake()->date(),
            'applied_on_packages' => fake()->text(191),
            'applied_on_business' => fake()->text(191),
            'is_active' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
