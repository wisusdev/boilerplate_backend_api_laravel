<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserContactAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\UserContactAccess::factory()->count(10)->create();
    }
}
