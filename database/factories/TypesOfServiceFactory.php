<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TypesOfServiceFactory extends Factory
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
            'description' => fake()->paragraph(),
            'business_id' => fake()->randomNumber(),
            'location_price_group' => fake()->paragraph(),
            'packing_charge' => fake()->randomFloat(4, 0, 999999999999999999),
            'packing_charge_type' => fake()->randomElement(['fixed','percent']),
            'enable_custom_fields' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
