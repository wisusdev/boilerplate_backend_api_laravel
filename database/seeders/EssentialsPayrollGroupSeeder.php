<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsPayrollGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsPayrollGroup::factory()->count(10)->create();
    }
}
