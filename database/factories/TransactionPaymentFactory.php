<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TransactionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => fake()->randomNumber(),
            'business_id' => fake()->randomNumber(),
            'is_return' => fake()->boolean(),
            'amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'method' => fake()->text(191),
            'transaction_no' => fake()->text(191),
            'payment_type' => fake()->text(191),
            'card_transaction_number' => fake()->text(191),
            'card_number' => fake()->text(191),
            'card_type' => fake()->text(191),
            'card_holder_name' => fake()->text(191),
            'card_month' => fake()->text(191),
            'card_year' => fake()->text(191),
            'card_security' => fake()->text(5),
            'cheque_number' => fake()->text(191),
            'bank_account_number' => fake()->text(191),
            'paid_on' => fake()->dateTime(),
            'created_by' => fake()->randomNumber(),
            'paid_through_link' => fake()->boolean(),
            'gateway' => fake()->text(191),
            'is_advance' => fake()->boolean(),
            'payment_for' => fake()->randomNumber(),
            'parent_id' => fake()->randomNumber(),
            'note' => fake()->text(191),
            'document' => fake()->text(191),
            'payment_ref_no' => fake()->text(191),
            'account_id' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
