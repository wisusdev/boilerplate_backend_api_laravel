<?php

namespace Database\Seeders;

use App\Models\ReferenceCount;
use Illuminate\Database\Seeder;

class ReferenceCountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ReferenceCount::factory()->count(10)->create();
    }
}
