<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SuperadminCommunicatorLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SuperadminCommunicatorLog::factory()->count(10)->create();
    }
}
