<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Categorías de gasto por defecto (datos de referencia, idempotente).
     * El icono usa nombres de bootstrap-icons.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Insumos',       'icon' => 'bi-box-seam'],
            ['name' => 'Gasolina',      'icon' => 'bi-fuel-pump'],
            ['name' => 'Salarios',      'icon' => 'bi-cash-coin'],
            ['name' => 'Carro',         'icon' => 'bi-car-front'],
            ['name' => 'Comida',        'icon' => 'bi-cup-hot'],
            ['name' => 'Entradas',      'icon' => 'bi-ticket-perforated'],
            ['name' => 'Hospedaje',     'icon' => 'bi-house-door'],
            ['name' => 'Parqueo',       'icon' => 'bi-p-square'],
            ['name' => 'Mantenimiento', 'icon' => 'bi-tools'],
        ];

        foreach ($categories as $i => $category) {
            ExpenseCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
