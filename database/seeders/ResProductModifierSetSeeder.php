<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ResProductModifierSetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ResProductModifierSet::factory()->count(10)->create();
    }
}
