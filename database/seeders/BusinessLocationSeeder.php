<?php

namespace Database\Seeders;

use App\Models\BusinessLocation;
use Illuminate\Database\Seeder;

class BusinessLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BusinessLocation::factory()->count(10)->create();
    }
}
