<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class OauthAuthCodeFactory extends Factory
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
            'client_id' => fake()->randomNumber(),
            'scopes' => fake()->paragraph(),
            'revoked' => fake()->boolean(),
            'expires_at' => fake()->dateTime(),
        ];
    }
}
