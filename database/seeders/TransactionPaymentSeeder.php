<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TransactionPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\TransactionPayment::factory()->count(10)->create();
    }
}
