<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EssentialsTodosUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EssentialsTodosUser::factory()->count(10)->create();
    }
}
