<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CashDenominationFactory extends Factory
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
            'amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'total_count' => fake()->randomNumber(),
            'model_type' => fake()->text(191),
            'model_id' => fake()->numerify(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
