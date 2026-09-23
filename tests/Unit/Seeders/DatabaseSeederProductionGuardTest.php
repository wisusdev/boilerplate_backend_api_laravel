<?php

namespace Tests\Unit\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión: `php artisan db:seed --force` (o el default `db:seed`) ya no
 * debe crear el admin de demostración `user00@wisus.dev` / `12345678aA_`
 * (contraseña fija y pública en el historial de git) cuando el entorno es
 * `production` — antes se creaba siempre, y era literalmente el paso que
 * varias guías de docs/deployment/*.md instruían ejecutar en producción.
 */
class DatabaseSeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Restaura el entorno real de test al terminar, para no filtrar hacia otros tests. */
    protected function tearDown(): void
    {
        app()->instance('env', 'testing');
        parent::tearDown();
    }

    public function test_user_seeder_no_crea_nada_en_produccion(): void
    {
        app()->instance('env', 'production');

        (new UserSeeder)->run();

        $this->assertSame(0, User::count());
    }

    public function test_user_seeder_si_crea_el_admin_de_demo_fuera_de_produccion(): void
    {
        app()->instance('env', 'testing');

        (new UserSeeder)->run();

        $admin = User::whereEmail('user00@wisus.dev')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_database_seeder_no_llama_a_los_seeders_de_demo_en_produccion(): void
    {
        app()->instance('env', 'production');

        (new DatabaseSeeder)->run();

        $this->assertSame(0, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'user00@wisus.dev']);
    }
}
