<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsLeafeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'essentials_leave_type_id' => fake()->randomNumber(),
            'business_id' => fake()->randomNumber(),
            'user_id' => fake()->randomNumber(),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'ref_no' => fake()->text(191),
            'status' => fake()->randomElement(['pending','approved','cancelled']),
            'reason' => fake()->paragraph(),
            'status_note' => fake()->paragraph(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
