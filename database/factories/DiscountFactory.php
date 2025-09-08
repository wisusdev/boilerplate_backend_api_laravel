<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class DiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->text(191),
            'business_id' => fake()->randomNumber(),
            'brand_id' => fake()->randomNumber(),
            'category_id' => fake()->randomNumber(),
            'location_id' => fake()->randomNumber(),
            'priority' => fake()->randomNumber(),
            'discount_type' => fake()->text(191),
            'discount_amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->dateTime(),
            'is_active' => fake()->boolean(),
            'spg' => fake()->text(100),
            'applicable_in_cg' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
