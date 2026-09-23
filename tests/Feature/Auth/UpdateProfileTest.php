<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\EmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Regresión: cambiar el email del propio perfil ahora exige la contraseña
 * actual (igual que cambiar la contraseña), revoca el resto de sesiones y
 * avisa a la dirección de correo ANTERIOR — antes bastaba un bearer token
 * (sin volver a probar la contraseña) para secuestrar la cuenta vía "cambiar
 * correo" + "olvidé mi contraseña" en el correo nuevo, sin que el dueño real
 * se enterara ni perdiera sus otras sesiones.
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'Test Personal Access Client',
            '--provider' => 'users',
            '--no-interaction' => true,
        ]);
    }

    private function createUser(string $email = 'user@example.com', string $password = 'password123'): User
    {
        return User::create([
            'username' => 'perfil'.uniqid(),
            'first_name' => 'Perfil',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt($password),
        ]);
    }

    private function loginToken(string $email, string $password): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'data' => ['type' => 'users', 'attributes' => ['email' => $email, 'password' => $password]],
        ]);

        return (string) $response->json('data.relationships.access.token');
    }

    private function patchProfile(string $token, array $attributes): TestResponse
    {
        return $this->call('PATCH', '/api/v1/account/profile', [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], json_encode(['data' => ['type' => 'profile', 'attributes' => $attributes]]));
    }

    private function getProfile(string $token): TestResponse
    {
        // El guard de Passport cachea el usuario autenticado en la instancia
        // del propio guard; dentro de un mismo test, dos $this->call() con
        // bearer tokens distintos reutilizarían esa caché si no se limpia,
        // dando un falso "autenticado" para un token ya revocado. En una
        // petición HTTP real cada una arranca su propio guard, así que esto
        // es puramente un artefacto de las pruebas.
        auth()->forgetGuards();

        return $this->call('GET', '/api/v1/account/profile', [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);
    }

    private function baseAttributes(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Perfil',
            'last_name' => 'User',
            'email' => 'user@example.com',
            'language' => 'es',
        ], $overrides);
    }

    public function test_actualizar_perfil_sin_cambiar_email_no_exige_password_actual(): void
    {
        $user = $this->createUser();
        $token = $this->loginToken('user@example.com', 'password123');

        $response = $this->patchProfile($token, $this->baseAttributes(['first_name' => 'Nuevo']));

        $response->assertOk();
        $this->assertSame('Nuevo', $user->fresh()->first_name);
    }

    public function test_cambiar_el_email_sin_password_actual_falla_422(): void
    {
        $this->createUser();
        $token = $this->loginToken('user@example.com', 'password123');

        $response = $this->patchProfile($token, $this->baseAttributes(['email' => 'nuevo@example.com']));

        $response->assertStatus(422);
        // Formato JSON:API de errores (ver JsonApiValidationErrorResponse):
        // {"errors": [{"title": campo, "detail": mensaje, "source": {...}}]}.
        $titles = collect($response->json('errors'))->pluck('title');
        $this->assertContains('data.attributes.current_password', $titles);
    }

    public function test_cambiar_el_email_con_password_actual_incorrecta_falla_422(): void
    {
        $this->createUser();
        $token = $this->loginToken('user@example.com', 'password123');

        $response = $this->patchProfile($token, $this->baseAttributes([
            'email' => 'nuevo@example.com',
            'current_password' => 'incorrecta',
        ]));

        $response->assertStatus(422);
    }

    public function test_cambiar_el_email_revoca_otras_sesiones_conserva_la_actual_y_avisa_al_correo_anterior(): void
    {
        Notification::fake();
        $user = $this->createUser();
        $tokenActual = $this->loginToken('user@example.com', 'password123');
        $tokenOtroDispositivo = $this->loginToken('user@example.com', 'password123');

        $response = $this->patchProfile($tokenActual, $this->baseAttributes([
            'email' => 'nuevo@example.com',
            'current_password' => 'password123',
        ]));

        $response->assertOk();
        $user->refresh();
        $this->assertSame('nuevo@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        // El token del "otro dispositivo" queda revocado...
        $this->getProfile($tokenOtroDispositivo)->assertStatus(401);

        // ...pero el token con el que se hizo el cambio sigue funcionando.
        $this->getProfile($tokenActual)->assertOk();

        // Se avisa a la dirección ANTERIOR, no solo a la nueva.
        Notification::assertSentOnDemand(
            EmailChangeNotification::class,
            fn ($notification, $channels, $notifiable) => in_array('user@example.com', $notifiable->routes, true)
        );
    }
}
