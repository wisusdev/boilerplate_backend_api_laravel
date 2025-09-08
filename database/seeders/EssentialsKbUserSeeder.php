<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsKbUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsKbUser::factory()->count(10)->create();
    }
}
