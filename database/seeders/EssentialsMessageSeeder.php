<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsMessage::factory()->count(10)->create();
    }
}
