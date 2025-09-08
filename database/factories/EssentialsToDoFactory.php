<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsToDoFactory extends Factory
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
            'task' => fake()->paragraph(),
            'date' => fake()->dateTime(),
            'end_date' => fake()->dateTime(),
            'task_id' => fake()->text(191),
            'description' => fake()->paragraph(),
            'status' => fake()->text(191),
            'estimated_hours' => fake()->text(191),
            'priority' => fake()->text(191),
            'created_by' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
