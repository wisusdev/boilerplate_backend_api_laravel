<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'description' => $this->faker->text,
            'max_users' => $this->faker->numberBetween(1, 10),
            'interval' => $this->faker->randomElement(['day', 'week', 'month', 'year']),
            'interval_count' => $this->faker->numberBetween(1, 10),
            'price' => $this->faker->randomFloat(2, 1, 100),
            'trial_days' => $this->faker->numberBetween(0, 30),
            'active' => $this->faker->boolean,
            'created_by' => User::factory(),
        ];
    }
}
