<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsAttendance::factory()->count(10)->create();
    }
}
