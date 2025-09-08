<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DashboardConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\DashboardConfiguration::factory()->count(10)->create();
    }
}
