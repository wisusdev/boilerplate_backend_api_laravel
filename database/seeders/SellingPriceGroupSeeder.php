<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SellingPriceGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SellingPriceGroup::factory()->count(10)->create();
    }
}
