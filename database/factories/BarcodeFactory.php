<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class BarcodeFactory extends Factory
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
            'description' => fake()->paragraph(),
            'width' => fake()->randomFloat(),
            'height' => fake()->randomFloat(),
            'paper_width' => fake()->randomFloat(),
            'paper_height' => fake()->randomFloat(),
            'top_margin' => fake()->randomFloat(),
            'left_margin' => fake()->randomFloat(),
            'row_distance' => fake()->randomFloat(),
            'col_distance' => fake()->randomFloat(),
            'stickers_in_one_row' => fake()->randomNumber(),
            'is_default' => fake()->boolean(),
            'is_continuous' => fake()->boolean(),
            'stickers_in_one_sheet' => fake()->randomNumber(),
            'business_id' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
