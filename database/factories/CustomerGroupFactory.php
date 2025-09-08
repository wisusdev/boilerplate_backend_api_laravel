<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CustomerGroupFactory extends Factory
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
            'name' => fake()->text(191),
            'amount' => fake()->randomFloat(),
            'price_calculation_type' => fake()->text(191),
            'selling_price_group_id' => fake()->randomNumber(),
            'created_by' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
