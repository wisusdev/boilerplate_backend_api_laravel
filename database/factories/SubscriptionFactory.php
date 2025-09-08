<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fake()->randomNumber(),
            'package_id' => fake()->randomNumber(),
            'start_date' => fake()->date(),
            'trial_end_date' => fake()->date(),
            'end_date' => fake()->date(),
            'package_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'original_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'coupon_code' => fake()->text(191),
            'package_details' => fake()->paragraph(),
            'created_id' => fake()->randomNumber(),
            'paid_via' => fake()->text(191),
            'payment_transaction_id' => fake()->text(191),
            'status' => fake()->randomElement(['approved','waiting','declined']),
            'deleted_at' => fake()->unixTime(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
