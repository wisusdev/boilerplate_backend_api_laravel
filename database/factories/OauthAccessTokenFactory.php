<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class OauthAccessTokenFactory extends Factory
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
            'name' => fake()->text(191),
            'scopes' => fake()->paragraph(),
            'revoked' => fake()->boolean(),
            'created_at' => fake()->unixTime(),
            'updated_at' => fake()->unixTime(),
            'expires_at' => fake()->dateTime(),
        ];
    }
}
