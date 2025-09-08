<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AccountTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => fake()->randomNumber(),
            'type' => fake()->randomElement(['debit','credit']),
            'sub_type' => fake()->randomElement(['opening_balance','fund_transfer','deposit']),
            'amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'reff_no' => fake()->text(191),
            'operation_date' => fake()->dateTime(),
            'created_by' => fake()->randomNumber(),
            'transaction_id' => fake()->randomNumber(),
            'transaction_payment_id' => fake()->randomNumber(),
            'transfer_transaction_id' => fake()->randomNumber(),
            'note' => fake()->paragraph(),
            'deleted_at' => fake()->unixTime(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
