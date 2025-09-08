<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsShift::factory()->count(10)->create();
    }
}
