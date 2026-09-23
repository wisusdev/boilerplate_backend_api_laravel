<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * En producción, `php artisan db:seed --force` debe sembrar solo la
     * infraestructura base (ajustes, permisos, roles). UserSeeder,
     * TravelModuleSeeder y StockImageSeeder son datos de DEMOSTRACIÓN: el
     * primero crea, entre otras cosas, un admin con contraseña fija y pública
     * (ver el guardia dentro de UserSeeder). Antes se ejecutaban siempre,
     * incluso en producción, contradiciendo lo que ya afirmaba
     * production-checklist.md sobre qué siembra el comando por defecto.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        if (app()->environment('production')) {
            return;
        }

        $this->call([
            UserSeeder::class,
            TravelModuleSeeder::class,
            // Descarga imágenes de stock (requiere red); resiliente si falla.
            StockImageSeeder::class,
        ]);
    }
}
