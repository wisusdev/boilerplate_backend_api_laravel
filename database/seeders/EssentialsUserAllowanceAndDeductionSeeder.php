<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsUserAllowanceAndDeductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsUserAllowanceAndDeduction::factory()->count(10)->create();
    }
}
