<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class PurchaseLineFactory extends Factory
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
            'pp_without_discount' => fake()->randomFloat(4, 0, 999999999999999999),
            'discount_percent' => fake()->randomFloat(2, 0, 999),
            'purchase_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'purchase_price_inc_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'item_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'tax_id' => fake()->randomNumber(),
            'purchase_requisition_line_id' => fake()->randomNumber(),
            'purchase_order_line_id' => fake()->randomNumber(),
            'quantity_sold' => fake()->randomFloat(4, 0, 999999999999999999),
            'quantity_adjusted' => fake()->randomFloat(4, 0, 999999999999999999),
            'quantity_returned' => fake()->randomFloat(4, 0, 999999999999999999),
            'po_quantity_purchased' => fake()->randomFloat(4, 0, 999999999999999999),
            'mfg_quantity_used' => fake()->randomFloat(4, 0, 999999999999999999),
            'mfg_date' => fake()->date(),
            'exp_date' => fake()->date(),
            'lot_number' => fake()->text(191),
            'sub_unit_id' => fake()->randomNumber(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
