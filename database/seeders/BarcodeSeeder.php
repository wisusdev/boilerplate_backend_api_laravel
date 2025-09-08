<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BarcodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Barcode::factory()->count(10)->create();
    }
}
