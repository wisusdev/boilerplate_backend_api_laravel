<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsKbFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fake()->numerify(),
            'title' => fake()->text(191),
            'content' => fake()->paragraph(),
            'status' => fake()->text(191),
            'kb_type' => fake()->text(191),
            'parent_id' => fake()->numerify(),
            'share_with' => fake()->text(191),
            'created_by' => fake()->numerify(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
