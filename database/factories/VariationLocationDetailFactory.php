<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class VariationLocationDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => fake()->randomNumber(),
            'product_variation_id' => fake()->randomNumber(),
            'variation_id' => fake()->randomNumber(),
            'location_id' => fake()->randomNumber(),
            'qty_available' => fake()->randomFloat(4, 0, 999999999999999999),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
