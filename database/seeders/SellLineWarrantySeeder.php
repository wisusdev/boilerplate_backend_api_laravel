<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SellLineWarrantySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SellLineWarranty::factory()->count(10)->create();
    }
}
