<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class OauthClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numerify(),
            'name' => fake()->text(191),
            'secret' => fake()->text(100),
            'provider' => fake()->text(191),
            'redirect' => fake()->paragraph(),
            'personal_access_client' => fake()->boolean(),
            'password_client' => fake()->boolean(),
            'revoked' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
        ];
    }
}
