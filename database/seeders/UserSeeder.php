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
     *
     * El guardia de abajo no es solo el comentario: DatabaseSeeder ya evita
     * llamar a este seeder en producción, pero un `db:seed --class=UserSeeder`
     * ejecutado a mano (o un futuro cambio en DatabaseSeeder) lo saltaría. Sin
     * este guardia, ese comando crea en producción una cuenta admin con
     * permisos totales y contraseña fija y pública (está en el historial de
     * git de este repo).
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn(
                'UserSeeder: omitido en production — crea un admin de demostración con contraseña fija. '
                .'El primer administrador se crea con el instalador (/install o `php artisan app:install`).'
            );

            return;
        }

        User::factory(10)->create();

        // Admin de demostración (solo desarrollo).
        Role::findOrCreate('admin', 'api');

        $admin = User::firstOrCreate(
            ['email' => 'user00@wisus.dev'],
            [
                'username' => 'user00',
                'first_name' => 'Jesus',
                'last_name' => 'Avelar',
                'password' => bcrypt('12345678aA_'),
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
