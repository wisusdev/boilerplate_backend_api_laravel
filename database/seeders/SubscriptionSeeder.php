<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId00 = User::where('username', 'user00')->first()->id;
        $userId01 = User::first()->id;


        $subscriptions = [
            [
                'user_id' => $userId01,
                'package_id' => 1,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'trial_ends_at' => null,
                'package_price' => 10.00,
                'package_details' => 'Package details',
                'created_by' => $userId00,
                'payment_method' => 'stripe',
                'payment_transaction_id' => 'stripe_transaction_id',
                'status' => 'approved',
            ],
            [
                'user_id' => $userId01,
                'package_id' => 2,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'trial_ends_at' => now()->addDays(7),
                'package_price' => 50.00,
                'package_details' => 'Package details',
                'created_by' => $userId00,
                'payment_method' => 'stripe',
                'payment_transaction_id' => 'stripe_transaction_id',
                'status' => 'approved',
            ],
            [
                'user_id' => $userId01,
                'package_id' => 3,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'trial_ends_at' => now()->addDays(14),
                'package_price' => 100.00,
                'package_details' => 'Package details',
                'created_by' => $userId00,
                'payment_method' => 'stripe',
                'payment_transaction_id' => 'stripe_transaction_id',
                'status' => 'approved',
            ],
        ];

        foreach ($subscriptions as $subscription) {
            Subscription::create($subscription);
        }
    }
}
