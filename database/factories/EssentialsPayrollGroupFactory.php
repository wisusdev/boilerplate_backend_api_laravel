<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsPayrollGroupFactory extends Factory
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
            'location_id' => fake()->randomNumber(),
            'name' => fake()->text(191),
            'status' => fake()->text(191),
            'payment_status' => fake()->text(191),
            'gross_total' => fake()->randomFloat(4, 0, 999999999999999999),
            'created_by' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
