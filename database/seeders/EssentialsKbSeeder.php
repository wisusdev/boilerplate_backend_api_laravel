<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsKbSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsKb::factory()->count(10)->create();
    }
}
