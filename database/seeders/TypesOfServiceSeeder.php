<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TypesOfServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\TypesOfService::factory()->count(10)->create();
    }
}
