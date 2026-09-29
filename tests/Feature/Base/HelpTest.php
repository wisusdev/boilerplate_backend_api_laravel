<?php

namespace Tests\Feature\Base;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Manual del administrador servido al panel (sección Ayuda).
 */
class HelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function comoRol(string $role): void
    {
        $user = User::create([
            'username' => $role.uniqid(), 'first_name' => 'Test', 'last_name' => 'Ayuda',
            'email' => uniqid($role).'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole($role);
        Passport::actingAs($user->fresh());
    }

    private function manual(): TestResponse
    {
        return $this->call('GET', '/api/v1/help/admin', [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ]);
    }

    public function test_el_equipo_recibe_el_manual_por_secciones(): void
    {
        $this->comoRol('editor');

        $res = $this->manual()->assertOk();

        $secciones = collect($res->json('data.attributes.sections'));
        $this->assertContains('reservas-y-cobros', $secciones->pluck('id'));
        $this->assertContains('Facturación electrónica (DTE)', $secciones->pluck('title'));
        // Markdown ya convertido: tablas incluidas.
        $this->assertStringContainsString('<table>', $secciones->firstWhere('id', 'catalogo')['html']);
        $this->assertStringContainsString('<p>', $res->json('data.attributes.intro'));
    }

    public function test_un_cliente_no_puede_leerlo(): void
    {
        $this->comoRol('user');

        $this->manual()->assertForbidden();
    }

    public function test_sin_sesion_no_hay_manual(): void
    {
        $this->manual()->assertUnauthorized();
    }
}
