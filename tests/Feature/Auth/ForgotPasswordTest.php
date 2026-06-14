<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ForgotPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(string $email = 'user@example.com'): User
    {
        return User::create([
            'username'   => 'testuser',
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'password'   => bcrypt('password123'),
        ]);
    }

    private function forgotPayload(string $email): array
    {
        return [
            'data' => [
                'type'       => 'users',
                'attributes' => compact('email'),
            ],
        ];
    }

    private function resetPayload(string $token, string $password = 'newpassword123'): array
    {
        return [
            'data' => [
                'type'       => 'reset-password',
                'attributes' => [
                    'token'                 => $token,
                    'password'              => $password,
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

    public function test_forgot_falla_con_email_no_registrado(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', $this->forgotPayload('nobody@example.com'));

        $response->assertStatus(422);
    }

    public function test_forgot_falla_sin_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'data' => ['type' => 'users', 'attributes' => []],
        ]);

        $response->assertJsonApiValidationErrors('data.attributes.email');
    }

    // ─── reset ────────────────────────────────────────────────────────────────

    public function test_reset_falla_con_token_invalido(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('invalid_token_xyz'));

        $response->assertStatus(422);
    }

    public function test_reset_falla_con_token_expirado(): void
    {
        $this->createUser('reset@example.com');

        DB::table('password_reset_tokens')->insert([
            'email'      => 'reset@example.com',
            'token'      => 'expired_token_abc',
            'created_at' => now()->subHours(12), // ya expiró
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('expired_token_abc'));

        $response->assertStatus(422);
    }

    public function test_reset_falla_cuando_contrasenas_no_coinciden(): void
    {
        $this->createUser('reset2@example.com');

        DB::table('password_reset_tokens')->insert([
            'email'      => 'reset2@example.com',
            'token'      => 'valid_token_123',
            'created_at' => now()->addHours(6),
        ]);

        $payload = [
            'data' => [
                'type'       => 'reset-password',
                'attributes' => [
                    'token'                 => 'valid_token_123',
                    'password'              => 'newpassword123',
                    'password_confirmation' => 'different_password',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/auth/reset-password', $payload);

        $response->assertStatus(422);
    }

    public function test_reset_falla_sin_token(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'data' => [
                'type'       => 'reset-password',
                'attributes' => [
                    'password'              => 'newpassword123',
                    'password_confirmation' => 'newpassword123',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
