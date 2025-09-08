<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\NotificationTemplate::factory()->count(10)->create();
    }
}
