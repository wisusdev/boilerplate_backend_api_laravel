<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => fake()->text(191),
            'description' => fake()->paragraph(),
            'subject_id' => fake()->randomNumber(),
            'subject_type' => fake()->text(191),
            'event' => fake()->text(191),
            'business_id' => fake()->randomNumber(),
            'causer_id' => fake()->randomNumber(),
            'causer_type' => fake()->text(191),
            'properties' => fake()->paragraph(),
            'batch_uuid' => fake()->regexify('[a-zA-Z0-9]{36}'),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
