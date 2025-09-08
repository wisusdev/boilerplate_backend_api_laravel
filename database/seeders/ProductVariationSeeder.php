<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductVariationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ProductVariation::factory()->count(10)->create();
    }
}
