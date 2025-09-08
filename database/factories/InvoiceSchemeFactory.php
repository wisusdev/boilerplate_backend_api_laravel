<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\InvoiceScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceScheme>
 */
class InvoiceSchemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::inRandomOrder()->first()->id,
            'name' => fake()->text(191),
            'scheme_type' => fake()->randomElement(['blank','year']),
            'number_type' => fake()->text(100),
            'prefix' => fake()->text(191),
            'start_number' => fake()->randomNumber(),
            'invoice_count' => fake()->randomNumber(),
            'total_digits' => fake()->randomNumber(),
            'is_default' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
