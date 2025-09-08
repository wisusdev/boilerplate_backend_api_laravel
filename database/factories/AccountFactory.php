<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => fake()->randomNumber(),
            'name' => fake()->text(191),
            'account_number' => fake()->text(191),
            'account_details' => fake()->paragraph(),
            'account_type_id' => fake()->randomNumber(),
            'note' => fake()->paragraph(),
            'created_by' => fake()->randomNumber(),
            'is_closed' => fake()->boolean(),
            'deleted_at' => fake()->unixTime(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
