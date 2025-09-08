<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsReminderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsReminder::factory()->count(10)->create();
    }
}
