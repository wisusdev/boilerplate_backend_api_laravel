<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ForgotPassword;
use App\Notifications\PasswordChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(string $email = 'user@example.com'): User
    {
        return User::create([
            'username' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password123'),
        ]);
    }

    private function forgotPayload(string $email): array
    {
        return [
            'data' => [
                'type' => 'users',
                'attributes' => compact('email'),
            ],
        ];
    }

    private function resetPayload(string $token, string $password = 'newpassword123'): array
    {
        return [
            'data' => [
                'type' => 'reset-password',
                'attributes' => [
                    'token' => $token,
                    'password' => $password,
                    'password_confirmation' => $password,
                ],
            ],
        ];
    }

    // ─── forgot ────────────────────────────────────────────────────────────────

    public function test_forgot_envia_notificacion_al_usuario(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/auth/forgot-password', $this->forgotPayload('user@example.com'));

        $response->assertOk();
        Notification::assertSentTo($user, ForgotPassword::class);
    }

    public function test_forgot_almacena_token_en_base_de_datos(): void
    {
        Notification::fake();
        $this->createUser();

        $this->postJson('/api/v1/auth/forgot-password', $this->forgotPayload('user@example.com'));

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_forgot_responde_igual_con_email_no_registrado(): void
    {
        Notification::fake();

        // Misma respuesta que con un correo existente: distinguirlas permitía
        // enumerar qué direcciones tienen cuenta.
        $response = $this->postJson('/api/v1/auth/forgot-password', $this->forgotPayload('nobody@example.com'));

        $response->assertOk();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@example.com']);
        Notification::assertNothingSent();
    }

    public function test_forgot_guarda_el_token_hasheado(): void
    {
        Notification::fake();
        $this->createUser('hashed@example.com');

        $this->postJson('/api/v1/auth/forgot-password', $this->forgotPayload('hashed@example.com'));

        $row = DB::table('password_reset_tokens')->where('email', 'hashed@example.com')->first();

        // 64 caracteres hex = sha256; el token en claro solo viaja en el correo.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row->token);
    }

    public function test_forgot_falla_sin_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'data' => ['type' => 'users', 'attributes' => []],
        ]);

        $response->assertJsonApiValidationErrors('data.attributes.email');
    }

    // ─── reset ────────────────────────────────────────────────────────────────

    public function test_reset_exitoso_cambia_password_y_notifica(): void
    {
        Notification::fake();
        $user = $this->createUser('reset-ok@example.com');

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset-ok@example.com',
            'token' => hash('sha256', 'valid_reset_token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('valid_reset_token', 'brandNew123'));

        $response->assertOk();
        // La contraseña realmente cambió.
        $this->assertTrue(Hash::check('brandNew123', $user->fresh()->password));
        // El token se consumió.
        $this->assertDatabaseMissing('password_reset_tokens', ['token' => hash('sha256', 'valid_reset_token')]);
        // Se notificó al usuario.
        Notification::assertSentTo($user, PasswordChangeNotification::class);
    }

    public function test_reset_falla_con_token_invalido(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('invalid_token_xyz'));

        $response->assertStatus(422);
    }

    public function test_reset_falla_con_token_expirado(): void
    {
        $this->createUser('reset@example.com');

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@example.com',
            'token' => hash('sha256', 'expired_token_abc'),
            'created_at' => now()->subHours(12), // ya expiró
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('expired_token_abc'));

        $response->assertStatus(422);
    }

    public function test_reset_falla_cuando_contrasenas_no_coinciden(): void
    {
        $this->createUser('reset2@example.com');

        DB::table('password_reset_tokens')->insert([
            'email' => 'reset2@example.com',
            'token' => hash('sha256', 'valid_token_123'),
            'created_at' => now(),
        ]);

        $payload = [
            'data' => [
                'type' => 'reset-password',
                'attributes' => [
                    'token' => 'valid_token_123',
                    'password' => 'newpassword123',
                    'password_confirmation' => 'different_password',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(422);
    }

    public function test_reset_rechaza_una_contrasena_corta(): void
    {
        $this->createUser('short@example.com');

        DB::table('password_reset_tokens')->insert([
            'email' => 'short@example.com',
            'token' => hash('sha256', 'short_pwd_token'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('short_pwd_token', 'abc'))
            ->assertStatus(422);
    }

    public function test_reset_revoca_las_sesiones_abiertas(): void
    {
        Notification::fake();
        $user = $this->createUser('revoke@example.com');
        // Token de sesión insertado directamente: en pruebas no hay personal
        // access client de Passport configurado.
        DB::table('oauth_access_tokens')->insert([
            'id' => 'token-previo-'.uniqid(),
            'user_id' => $user->id,
            'client_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'sesion previa',
            'scopes' => '[]',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addWeek(),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'revoke@example.com',
            'token' => hash('sha256', 'revoke_token'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('revoke_token', 'brandNew123'))
            ->assertOk();

        $this->assertSame(0, $user->tokens()->where('revoked', false)->count());
    }

    public function test_reset_falla_sin_token(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'data' => [
                'type' => 'reset-password',
                'attributes' => [
                    'password' => 'newpassword123',
                    'password_confirmation' => 'newpassword123',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
