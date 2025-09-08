<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VariationGroupPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\VariationGroupPrice::factory()->count(10)->create();
    }
}
