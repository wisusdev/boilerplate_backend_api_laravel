<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EssentialsReminderFactory extends Factory
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
            'user_id' => fake()->randomNumber(),
            'name' => fake()->text(191),
            'date' => fake()->date(),
            'time' => fake()->time(),
            'end_time' => fake()->time(),
            'repeat' => fake()->randomElement(['one_time','every_day','every_week','every_month']),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
