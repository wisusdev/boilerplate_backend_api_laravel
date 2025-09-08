<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StockAdjustmentsTempSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\StockAdjustmentsTemp::factory()->count(10)->create();
    }
}
