<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsAttendanceFactory extends Factory
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
            'business_id' => fake()->randomNumber(),
            'clock_in_time' => fake()->dateTime(),
            'clock_out_time' => fake()->dateTime(),
            'essentials_shift_id' => fake()->randomNumber(),
            'ip_address' => fake()->text(191),
            'clock_in_note' => fake()->paragraph(),
            'clock_out_note' => fake()->paragraph(),
            'clock_in_location' => fake()->paragraph(),
            'clock_out_location' => fake()->paragraph(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
