<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\ReferenceCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceCount>
 */
class ReferenceCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::inRandomOrder()->first()->id ?? Business::factory(),
            'ref_type' => fake()->randomElement([
                'contacts',
                'business_location',
                'username',
                'selling_price_group',
                'product',
                'purchase_order',
                'sell',
                'invoice',
                'expense',
                'stock_adjustment',
                'stock_transfer',
                'stock_transfer_draft',
                'stock_transfer_completed',
                'stock_transfer_rejected',
                'stock_transfer_pending',
                'stock_transfer_approved',
                'stock_transfer_received',
            ]),
            'ref_count' => fake()->numberBetween(1, 100),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
