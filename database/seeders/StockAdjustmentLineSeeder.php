<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StockAdjustmentLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\StockAdjustmentLine::factory()->count(10)->create();
    }
}
