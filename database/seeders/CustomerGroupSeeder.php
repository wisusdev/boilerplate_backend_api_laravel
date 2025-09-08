<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\CustomerGroup::factory()->count(10)->create();
    }
}
