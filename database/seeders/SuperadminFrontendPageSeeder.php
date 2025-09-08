<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SuperadminFrontendPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SuperadminFrontendPage::factory()->count(10)->create();
    }
}
