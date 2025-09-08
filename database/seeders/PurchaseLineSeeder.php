<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PurchaseLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\PurchaseLine::factory()->count(10)->create();
    }
}
