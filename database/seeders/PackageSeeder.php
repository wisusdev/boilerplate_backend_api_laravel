<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::where('username', 'user00')->first()->id;

        $packages = [
            [
                'name' => 'Basic',
                'description' => 'Basic package',
                'max_users' => 1,
                'interval' => 'month',
                'interval_count' => 1,
                'price' => 10.00,
                'trial_days' => 0,
                'active' => true,
                'created_by' => $userId,
            ],
            [
                'name' => 'Pro',
                'description' => 'Pro package',
                'max_users' => 5,
                'interval' => 'month',
                'interval_count' => 1,
                'price' => 50.00,
                'trial_days' => 7,
                'active' => true,
                'created_by' => $userId,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Enterprise package',
                'max_users' => 10,
                'interval' => 'month',
                'interval_count' => 1,
                'price' => 100.00,
                'trial_days' => 14,
                'active' => true,
                'created_by' => $userId,
            ],
        ];

        foreach ($packages as $package) {
            Package::create($package);
        }
    }
}
