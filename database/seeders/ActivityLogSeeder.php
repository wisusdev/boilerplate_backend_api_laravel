<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ActivityLog::factory()->count(10)->create();
    }
}
