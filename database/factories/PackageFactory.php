<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
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
            'name' => fake()->text(191),
            'description' => fake()->paragraph(),
            'location_count' => fake()->randomNumber(),
            'user_count' => fake()->randomNumber(),
            'product_count' => fake()->randomNumber(),
            'bookings' => fake()->boolean(),
            'kitchen' => fake()->boolean(),
            'order_screen' => fake()->boolean(),
            'tables' => fake()->boolean(),
            'invoice_count' => fake()->randomNumber(),
            'interval' => fake()->randomElement(['days','months','years']),
            'interval_count' => fake()->randomNumber(),
            'trial_days' => fake()->randomNumber(),
            'price' => fake()->randomFloat(4, 0, 999999999999999999),
            'custom_permissions' => fake()->paragraph(),
            'created_by' => fake()->randomNumber(),
            'sort_order' => fake()->randomNumber(),
            'is_active' => fake()->boolean(),
            'mark_package_as_popular' => fake()->boolean(),
            'businesses' => fake()->paragraph(),
            'is_private' => fake()->boolean(),
            'is_one_time' => fake()->boolean(),
            'enable_custom_link' => fake()->boolean(),
            'custom_link' => fake()->text(191),
            'custom_link_text' => fake()->text(191),
            'deleted_at' => fake()->unixTime(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
