<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class VariationGroupPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variation_id' => fake()->randomNumber(),
            'price_group_id' => fake()->randomNumber(),
            'price_inc_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'price_type' => fake()->text(191),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
