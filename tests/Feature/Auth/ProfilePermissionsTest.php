<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * El perfil propio trae roles y permisos actuales: el panel los refresca al
 * cargar, así un permiso nuevo (o retirado) no espera a que el usuario vuelva
 * a iniciar sesión.
 */
class ProfilePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_perfil_trae_los_roles_y_permisos_actuales(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $user = User::create([
            'username' => 'ed'.uniqid(), 'first_name' => 'Editor', 'last_name' => 'P',
            'email' => uniqid('ed').'@example.com', 'password' => bcrypt('password123'),
        ]);
        $user->assignRole('editor');
        Passport::actingAs($user->fresh());

        $attrs = $this->getJson('/api/v1/account/profile', ['Accept' => 'application/vnd.api+json'])
            ->assertOk()
            ->json('data.attributes');

        $this->assertSame(['editor'], $attrs['roles']);
        $this->assertContains('map-pins:store', $attrs['permissions']);
        $this->assertNotContains('invoices:index', $attrs['permissions']);
    }
}
