<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CashRegisterFactory extends Factory
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
            'user_id' => fake()->randomNumber(),
            'status' => fake()->randomElement(['close','open']),
            'closed_at' => fake()->dateTime(),
            'closing_amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'total_card_slips' => fake()->randomNumber(),
            'total_cheques' => fake()->randomNumber(),
            'denominations' => fake()->paragraph(),
            'closing_note' => fake()->paragraph(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
