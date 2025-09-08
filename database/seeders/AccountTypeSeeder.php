<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\AccountType::factory()->count(10)->create();
    }
}
