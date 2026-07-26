<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seeder de usuarios de DEMOSTRACIÓN (solo desarrollo). Además de usuarios de
     * prueba, crea un ADMIN de demostración para que un `db:seed` deje la app lista
     * (evita tener que pasar por el instalador en desarrollo).
     *
     * En producción NO se usa este seeder: el primer administrador se crea con el
     * instalador (wizard web /install o `php artisan app:install`).
     */
    public function run(): void
    {
        User::factory(10)->create();

        // Admin de demostración (solo desarrollo).
        Role::findOrCreate('admin', 'api');

        $admin = User::firstOrCreate(
            ['email' => 'admin@cusgo.com'],
            [
                'username' => 'admin',
                'first_name' => 'Admin',
                'last_name' => 'Cusgo',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // Marca la app como instalada (coherente con el flag del instalador).
        $app = json_decode(optional(Setting::where('key', 'app')->first())->value ?? '{}', true) ?: [];
        $app['installed'] = true;
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode($app)]);
    }
}
