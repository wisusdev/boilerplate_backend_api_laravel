<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsToDoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsToDo::factory()->count(10)->create();
    }
}
