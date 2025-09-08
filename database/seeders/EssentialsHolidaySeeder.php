<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsHolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsHoliday::factory()->count(10)->create();
    }
}
