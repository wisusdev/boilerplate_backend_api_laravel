<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        // RefreshDatabase vacía oauth_clients en cada test; el login emite tokens
        // mediante un personal access client que debe existir.
        $this->artisan('passport:client', [
            '--personal'       => true,
            '--name'           => 'Test Personal Access Client',
            '--provider'       => 'users',
            '--no-interaction' => true,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(string $email = 'user@example.com', string $password = 'password123'): User
    {
        return User::create([
            'username'   => 'testuser',
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'password'   => bcrypt($password),
        ]);
    }

    private function loginPayload(string $email, string $password): array
    {
        return [
            'data' => [
                'type'       => 'users',
                'attributes' => compact('email', 'password'),
            ],
        ];
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_login_con_credenciales_validas_retorna_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload('user@example.com', 'password123'));

        $response->assertOk();
        $response->assertJsonPath('data.relationships.access.token_type', 'Bearer');
        $this->assertNotEmpty($response->json('data.relationships.access.token'));
    }

    public function test_login_con_contrasena_incorrecta_retorna_422(): void
    {
        $this->createUser('user@example.com', 'correct_pass');

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload('user@example.com', 'wrong_pass'));

        $response->assertStatus(422);
    }

    public function test_login_con_email_inexistente_retorna_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload('nobody@example.com', 'password123'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.email']);
    }

    public function test_login_sin_email_retorna_validacion_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'data' => [
                'type'       => 'users',
                'attributes' => ['password' => 'password123'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.email']);
    }

    public function test_login_sin_contrasena_retorna_validacion_422(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'data' => [
                'type'       => 'users',
                'attributes' => ['email' => 'user@example.com'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.password']);
    }

    public function test_login_con_email_malformado_retorna_validacion_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload('not-an-email', 'password123'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data.attributes.email']);
    }

    public function test_login_sin_body_retorna_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }
}
