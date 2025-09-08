<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DiscountVariationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\DiscountVariation::factory()->count(10)->create();
    }
}
