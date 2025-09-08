<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AccountTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\AccountTransaction::factory()->count(10)->create();
    }
}
