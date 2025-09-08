<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class VariationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->text(191),
            'product_id' => fake()->randomNumber(),
            'sub_sku' => fake()->text(191),
            'product_variation_id' => fake()->randomNumber(),
            'variation_value_id' => fake()->randomNumber(),
            'default_purchase_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'dpp_inc_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'profit_percent' => fake()->randomFloat(4, 0, 999999999999999999),
            'default_sell_price' => fake()->randomFloat(4, 0, 999999999999999999),
            'sell_price_inc_tax' => fake()->randomFloat(4, 0, 999999999999999999),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
            'deleted_at' => fake()->unixTime(),
            'combo_variations' => fake()->paragraph(),
        ];
    }
}
