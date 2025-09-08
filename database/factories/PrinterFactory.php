<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
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
            'created_by' => User::inRandomOrder()->first()->id ?? User::factory(),
            'name' => fake()->text(191),
            'connection_type' => fake()->randomElement(['network','windows','linux']),
            'capability_profile' => fake()->randomElement(['default','simple','SP2000','TEP-200M','P822D']),
            'char_per_line' => fake()->text(191),
            'ip_address' => fake()->text(191),
            'port' => fake()->text(191),
            'path' => fake()->text(191),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
