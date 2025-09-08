<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ProductLocation::factory()->count(10)->create();
    }
}
