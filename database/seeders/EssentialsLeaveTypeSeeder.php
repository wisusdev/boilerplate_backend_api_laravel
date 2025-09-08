<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsLeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsLeaveType::factory()->count(10)->create();
    }
}
