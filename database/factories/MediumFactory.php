<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class MediumFactory extends Factory
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
            'file_name' => fake()->text(191),
            'description' => fake()->paragraph(),
            'uploaded_by' => fake()->randomNumber(),
            'model_type' => fake()->text(191),
            'model_media_type' => fake()->text(191),
            'model_id' => fake()->numerify(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
