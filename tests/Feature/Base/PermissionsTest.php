<?php

namespace Tests\Feature\Base;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function apiJson(string $method, string $uri): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ]);
    }

    public function test_un_admin_lista_el_catalogo_de_permisos(): void
    {
        $admin = User::create([
            'username' => 'adm'.uniqid(), 'first_name' => 'Admin', 'last_name' => 'P',
            'email' => uniqid('adm').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $admin->assignRole('admin');
        Passport::actingAs($admin->fresh());

        $nombres = $this->apiJson('GET', '/api/v1/permissions')
            ->assertOk()
            ->json('data.attributes.*.name');

        $this->assertContains('tours:store', $nombres);
        // Limpiados por la migración de vestigiales (ver C-5).
        $this->assertNotContains('roles:create', $nombres);
        $this->assertNotContains('users:edit', $nombres);
    }

    public function test_un_usuario_sin_permiso_no_puede_listar_permisos(): void
    {
        $user = User::create([
            'username' => 'cli'.uniqid(), 'first_name' => 'C', 'last_name' => 'L',
            'email' => uniqid('cli').'@example.com', 'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        $this->apiJson('GET', '/api/v1/permissions')->assertForbidden();
    }

    public function test_requiere_autenticacion(): void
    {
        $this->apiJson('GET', '/api/v1/permissions')->assertUnauthorized();
    }
}
