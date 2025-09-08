<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ResTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ResTable::factory()->count(10)->create();
    }
}
