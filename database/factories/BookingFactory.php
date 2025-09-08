<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => fake()->randomNumber(),
            'waiter_id' => fake()->randomNumber(),
            'table_id' => fake()->randomNumber(),
            'correspondent_id' => fake()->randomNumber(),
            'business_id' => fake()->randomNumber(),
            'location_id' => fake()->randomNumber(),
            'booking_start' => fake()->dateTime(),
            'booking_end' => fake()->dateTime(),
            'created_by' => fake()->randomNumber(),
            'booking_status' => fake()->text(191),
            'booking_note' => fake()->paragraph(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
