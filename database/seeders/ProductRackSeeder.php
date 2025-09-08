<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductRackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ProductRack::factory()->count(10)->create();
    }
}
