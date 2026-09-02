<?php

namespace Tests\Feature\Base;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Regresiones de la auditoría de seguridad (ver AUDITORIA-SEGURIDAD.md).
 * Cada test fija el comportamiento correcto de un hallazgo ya corregido.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    private function makeUser(?string $role = null, string $prefix = 'u'): User
    {
        $user = User::create([
            'username' => $prefix.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid($prefix).'@example.com',
            'password' => bcrypt('password123'),
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    // ─── A-5: verificación de correo ──────────────────────────────────────────

    public function test_la_verificacion_de_correo_exige_enlace_firmado(): void
    {
        $user = $this->makeUser();

        // Antes bastaba con adivinar el id: /auth/email/verify/{id}/loquesea
        $this->getJson('/api/v1/auth/email/verify/'.$user->id.'/loquesea')
            ->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_la_verificacion_de_correo_exige_el_hash_correcto(): void
    {
        $user = $this->makeUser();

        // Enlace correctamente firmado, pero con el hash de otro correo.
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('otro@example.com'),
        ], false);

        $this->getJson($url)->assertStatus(422);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_la_verificacion_de_correo_funciona_con_un_enlace_valido(): void
    {
        $user = $this->makeUser();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ], false);

        $this->getJson($url)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    // ─── A-4: escalada de privilegios ─────────────────────────────────────────

    public function test_no_se_puede_otorgar_un_rol_con_permisos_que_no_se_poseen(): void
    {
        // 'editor' no tiene permisos de usuarios; se le añaden solo los de gestión
        // para reproducir el escenario del hallazgo.
        $editorRole = Role::where('name', 'editor')->where('guard_name', 'api')->first();
        $editorRole->givePermissionTo(['users:index', 'users:update', 'users:show']);

        $editor = $this->makeUser('editor', 'editor');
        $victim = $this->makeUser('user', 'victim');
        Passport::actingAs($editor->fresh());

        $response = $this->apiJson('PATCH', '/api/v1/users/'.$victim->id, [
            'data' => [
                'id' => (string) $victim->id,
                'type' => 'users',
                'attributes' => [
                    'username' => $victim->username,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => $victim->email,
                    'roles' => ['admin'],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertFalse($victim->fresh()->hasRole('admin'));
    }

    public function test_no_se_pueden_otorgar_permisos_que_no_se_poseen_a_un_rol(): void
    {
        $editorRole = Role::where('name', 'editor')->where('guard_name', 'api')->first();
        $editorRole->givePermissionTo(['roles:index', 'roles:update', 'roles:show']);

        $editor = $this->makeUser('editor', 'editor');
        Passport::actingAs($editor->fresh());

        // Reconfigurar su propio rol para concederse permisos de administración.
        $response = $this->apiJson('PATCH', '/api/v1/roles/'.$editorRole->getRouteKey(), [
            'data' => [
                'id' => (string) $editorRole->getRouteKey(),
                'type' => 'roles',
                'attributes' => [
                    'name' => 'editor',
                    'permissions' => ['users:store', 'settings:update'],
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertFalse($editorRole->fresh()->hasPermissionTo('settings:update'));
    }

    public function test_un_admin_si_puede_asignar_roles_dentro_de_sus_permisos(): void
    {
        $admin = $this->makeUser('admin', 'admin');
        $target = $this->makeUser('user', 'target');
        Passport::actingAs($admin->fresh());

        $this->apiJson('PATCH', '/api/v1/users/'.$target->id, [
            'data' => [
                'id' => (string) $target->id,
                'type' => 'users',
                'attributes' => [
                    'username' => $target->username,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => $target->email,
                    'roles' => ['editor'],
                ],
            ],
        ])->assertOk();

        $this->assertTrue($target->fresh()->hasRole('editor'));
    }

    // ─── M-5: validación del update de usuarios ───────────────────────────────

    public function test_el_update_de_usuario_valida_el_correo(): void
    {
        $admin = $this->makeUser('admin', 'admin');
        $otro = $this->makeUser('user', 'otro');
        $target = $this->makeUser('user', 'target');
        Passport::actingAs($admin->fresh());

        // Las reglas anidadas hacían que Laravel ignorara formato y unicidad.
        $this->apiJson('PATCH', '/api/v1/users/'.$target->id, [
            'data' => [
                'id' => (string) $target->id,
                'type' => 'users',
                'attributes' => [
                    'username' => $target->username,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => 'no-es-un-correo',
                    'roles' => [],
                ],
            ],
        ])->assertStatus(422);

        $this->apiJson('PATCH', '/api/v1/users/'.$target->id, [
            'data' => [
                'id' => (string) $target->id,
                'type' => 'users',
                'attributes' => [
                    'username' => $target->username,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => $otro->email, // duplicado
                    'roles' => [],
                ],
            ],
        ])->assertStatus(422);
    }

    // ─── A-3: secretos de pasarela ────────────────────────────────────────────

    public function test_los_secretos_de_pasarela_no_salen_en_claro_para_un_admin(): void
    {
        // PayPal y Stripe se retiraron del producto; Wompi es la única pasarela
        // en línea que queda y ejerce el mismo mecanismo de enmascarado.
        Setting::create(['key' => 'payment_gateway', 'value' => json_encode([
            'wompi_private_key' => 'priv_live_supersecreto1234',
            'wompi_public_key' => 'pub_live_publica',
        ])]);

        Passport::actingAs($this->makeUser('admin', 'admin')->fresh());

        $attributes = $this->apiJson('GET', '/api/v1/settings')
            ->assertOk()
            ->json('data.attributes');

        $this->assertStringNotContainsString('priv_live_supersecreto1234', json_encode($attributes));
        $this->assertTrue($attributes['wompi_private_key_configured']);
        // La clave publicable sí es visible: la necesita el checkout.
        $this->assertSame('pub_live_publica', $attributes['wompi_public_key']);
    }

    public function test_guardar_ajustes_sin_tocar_el_secreto_lo_conserva(): void
    {
        Setting::create(['key' => 'payment_gateway', 'value' => json_encode([
            'wompi_private_key' => 'priv_live_supersecreto1234',
        ])]);

        Passport::actingAs($this->makeUser('admin', 'admin')->fresh());

        // El formulario reenvía el valor enmascarado (o vacío) cuando no se edita.
        $this->apiJson('PATCH', '/api/v1/settings', [
            'data' => ['type' => 'settings', 'attributes' => [
                'wompi_private_key' => '',
                'wompi_mode' => 'live',
            ]],
        ])->assertOk();

        $stored = json_decode(Setting::where('key', 'payment_gateway')->first()->value, true);
        $this->assertSame('priv_live_supersecreto1234', $stored['wompi_private_key']);
        $this->assertSame('live', $stored['wompi_mode']);
    }

    // ─── M-7: tamaño de página acotado ────────────────────────────────────────

    public function test_el_tamano_de_pagina_esta_acotado(): void
    {
        Passport::actingAs($this->makeUser('admin', 'admin')->fresh());

        $meta = $this->apiJson('GET', '/api/v1/users?page[size]=1000000')
            ->assertOk()
            ->json('meta');

        $this->assertLessThanOrEqual(100, $meta['per_page']);
    }

    // ─── M-6: sparse fieldset acotado ─────────────────────────────────────────

    public function test_el_sparse_fieldset_no_expone_columnas_ocultas(): void
    {
        Passport::actingAs($this->makeUser('admin', 'admin')->fresh());

        $response = $this->apiJson('GET', '/api/v1/users?fields[users]=email,password,remember_token')
            ->assertOk();

        $this->assertStringNotContainsString('password', json_encode($response->json('data')));
    }
}
