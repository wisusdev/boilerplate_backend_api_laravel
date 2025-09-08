<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VariationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Variation::factory()->count(10)->create();
    }
}
