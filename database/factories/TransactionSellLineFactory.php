<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TransactionSellLineFactory extends Factory
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
            'quantity_returned' => fake()->randomFloat(4, 0, 9999999999999999),
            'unit_price_before_discount' => fake()->randomFloat(4, 0, 999999999999999999),
            'unit_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'line_discount_type' => fake()->randomElement(['fixed','percentage']),
            'line_discount_amount' => fake()->randomFloat(4, 0, 999999999999999999),
            'unit_price_inc_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'item_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'tax_id' => fake()->randomNumber(),
            'discount_id' => fake()->randomNumber(),
            'lot_no_line_id' => fake()->randomNumber(),
            'sell_line_note' => fake()->paragraph(),
            'so_line_id' => fake()->randomNumber(),
            'so_quantity_invoiced' => fake()->randomFloat(4, 0, 999999999999999999),
            'res_service_staff_id' => fake()->randomNumber(),
            'res_line_order_status' => fake()->text(191),
            'parent_sell_line_id' => fake()->randomNumber(),
            'children_type' => fake()->text(191),
            'sub_unit_id' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
