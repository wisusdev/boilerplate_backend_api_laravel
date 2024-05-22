<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'package_id' => 1,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'trial_ends_at' => null,
            'package_price' => 10.00,
            'package_details' => 'Package details',
            'created_by' => User::factory(),
            'payment_method' => 'stripe',
            'payment_transaction_id' => 'stripe_transaction_id',
            'status' => 'approved',
        ];
    }
}
