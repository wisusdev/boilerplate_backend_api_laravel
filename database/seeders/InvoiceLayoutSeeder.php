<?php

namespace Database\Seeders;

use App\Models\InvoiceLayout;
use Illuminate\Database\Seeder;

class InvoiceLayoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        InvoiceLayout::factory()->count(10)->create();
    }
}
