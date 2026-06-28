<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El rol 'user' debe existir para que RegisterController pueda asignarlo
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validPayload(array $override = []): array
    {
        $base = [
            'data' => [
                'type'       => 'users',
                'attributes' => [
                    'username'              => 'johndoe',
                    'first_name'            => 'John',
                    'last_name'             => 'Doe',
                    'email'                 => 'john@example.com',
                    'password'              => 'secure123',
                    'password_confirmation' => 'secure123',
                ],
            ],
        ];

        foreach ($override as $key => $value) {
            data_set($base, $key, $value);
        }

        return $base;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_registro_exitoso_crea_usuario_en_base_de_datos(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email'    => 'john@example.com',
            'username' => 'johndoe',
        ]);
    }

    public function test_registro_sin_username_genera_uno_automatico(): void
    {
        Notification::fake();

        $payload = $this->validPayload();
        unset($payload['data']['attributes']['username']);

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertCreated();
        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->username);
        // Se deriva del local-part del email cuando no se envía username.
        $this->assertSame('john', $user->username);
    }

    public function test_registro_falla_con_email_duplicado(): void
    {
        Notification::fake();
        User::create([
            'username'   => 'existing',
            'first_name' => 'Existing',
            'last_name'  => 'User',
            'email'      => 'john@example.com',
            'password'   => bcrypt('pass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.email']);
    }

    public function test_registro_falla_con_username_duplicado(): void
    {
        Notification::fake();
        User::create([
            'username'   => 'johndoe',
            'first_name' => 'Other',
            'last_name'  => 'User',
            'email'      => 'other@example.com',
            'password'   => bcrypt('pass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.username']);
    }

    public function test_registro_falla_cuando_contrasenas_no_coinciden(): void
    {
        $payload = $this->validPayload();
        $payload['data']['attributes']['password_confirmation'] = 'different_password';

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.password']);
    }

    public function test_registro_falla_con_contrasena_demasiado_corta(): void
    {
        $payload = $this->validPayload();
        $payload['data']['attributes']['password']              = 'short';
        $payload['data']['attributes']['password_confirmation'] = 'short';

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.password']);
    }

    public function test_registro_falla_sin_campos_requeridos(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'data' => [
                'type'       => 'users',
                'attributes' => [],
            ],
        ]);

        $response->assertStatus(422);
        // username ya NO es requerido (se autogenera si no se envía).
        $response->assertJsonValidationErrors([
            'data.attributes.email',
            'data.attributes.first_name',
            'data.attributes.last_name',
            'data.attributes.password',
        ]);
        $response->assertJsonMissingValidationErrors(['data.attributes.username']);
    }

    public function test_registro_falla_con_email_malformado(): void
    {
        $payload = $this->validPayload();
        $payload['data']['attributes']['email'] = 'not-a-valid-email';

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422);
    }
}
