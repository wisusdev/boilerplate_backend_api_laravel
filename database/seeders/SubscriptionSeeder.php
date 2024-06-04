<?php

namespace Database\Seeders;

use App\Models\Package;
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

        $package01 = Package::where('name', 'Basic')->first();
        $package02 = Package::where('name', 'Pro')->first();
        $package03 = Package::where('name', 'Enterprise')->first();

        $subscriptions = [
            [
                'user_id' => $userId01,
                'package_id' => $package01->id,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'trial_ends_at' => null,
                'package_price' => $package01->price,
                'package_details' => $package01->description,
                'created_by' => $userId00,
                'payment_method' => 'stripe',
                'payment_transaction_id' => 'stripe_transaction_id',
                'status' => 'approved',
            ],
            [
                'user_id' => $userId01,
                'package_id' => $package02->id,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'trial_ends_at' => now()->addDays(7),
                'package_price' => $package02->price,
                'package_details' => $package02->description,
                'created_by' => $userId00,
                'payment_method' => 'stripe',
                'payment_transaction_id' => 'stripe_transaction_id',
                'status' => 'approved',
            ],
            [
                'user_id' => $userId01,
                'package_id' => $package03->id,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'trial_ends_at' => now()->addDays(14),
                'package_price' => $package03->price,
                'package_details' => $package03->description,
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
