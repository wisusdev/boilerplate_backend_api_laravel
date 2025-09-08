<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsUserSalesTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->randomNumber(),
            'target_start' => fake()->randomFloat(4, 0, 999999999999999999),
            'target_end' => fake()->randomFloat(4, 0, 999999999999999999),
            'commission_percent' => fake()->randomFloat(4, 0, 999999999999999999),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
