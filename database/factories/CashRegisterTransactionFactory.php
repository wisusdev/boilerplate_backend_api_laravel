<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CashRegisterTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => fake()->randomNumber(),
            'amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'pay_method' => fake()->text(191),
            'type' => fake()->randomElement(['debit','credit']),
            'transaction_type' => fake()->text(191),
            'transaction_id' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
