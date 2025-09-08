<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsAllowancesAndDeductionFactory extends Factory
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
            'description' => fake()->text(191),
            'type' => fake()->randomElement(['allowance','deduction']),
            'amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'amount_type' => fake()->randomElement(['fixed','percent']),
            'applicable_date' => fake()->date(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
