<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsUserSalesTargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsUserSalesTarget::factory()->count(10)->create();
    }
}
