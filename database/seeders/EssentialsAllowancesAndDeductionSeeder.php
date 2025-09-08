<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsAllowancesAndDeductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsAllowancesAndDeduction::factory()->count(10)->create();
    }
}
