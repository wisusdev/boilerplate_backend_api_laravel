<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Discount::factory()->count(10)->create();
    }
}
