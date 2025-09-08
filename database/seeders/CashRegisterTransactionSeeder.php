<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CashRegisterTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\CashRegisterTransaction::factory()->count(10)->create();
    }
}
