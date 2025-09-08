<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class StockAdjustmentLineFactory extends Factory
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
            'product_id' => fake()->randomNumber(),
            'variation_id' => fake()->randomNumber(),
            'quantity' => fake()->randomFloat(4, 0, 999999999999999999),
            'secondary_unit_quantity' => fake()->randomFloat(4, 0, 999999999999999999),
            'unit_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'removed_purchase_line' => fake()->randomNumber(),
            'lot_no_line_id' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
