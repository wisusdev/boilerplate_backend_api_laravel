<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsShiftFactory extends Factory
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
            'type' => fake()->randomElement(['fixed_shift','flexible_shift']),
            'business_id' => fake()->randomNumber(),
            'start_time' => fake()->time(),
            'end_time' => fake()->time(),
            'is_allowed_auto_clockout' => fake()->boolean(),
            'auto_clockout_time' => fake()->time(),
            'holidays' => fake()->paragraph(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
