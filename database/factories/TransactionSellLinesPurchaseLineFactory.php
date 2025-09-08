<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TransactionSellLinesPurchaseLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sell_line_id' => fake()->randomNumber(),
            'stock_adjustment_line_id' => fake()->randomNumber(),
            'purchase_line_id' => fake()->randomNumber(),
            'quantity' => fake()->randomFloat(4, 0, 999999999999999999),
            'qty_returned' => fake()->randomFloat(4, 0, 999999999999999999),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
