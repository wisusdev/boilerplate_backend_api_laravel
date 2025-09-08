<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsUserShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsUserShift::factory()->count(10)->create();
    }
}
