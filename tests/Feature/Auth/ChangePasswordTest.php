<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function patchJsonApi(string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->call('PATCH', $uri, [], [], [], [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode($data));
    }

    private function makeUser(string $password = 'OldPass123'): User
    {
        return User::create([
            'username'   => 'changepw',
            'first_name' => 'Change',
            'last_name'  => 'Pw',
            'email'      => 'changepw@example.com',
            'password'   => $password, // el cast 'hashed' lo encripta
        ]);
    }

    public function test_cambiar_password_no_requiere_data_id(): void
    {
        Notification::fake();
        $user = $this->makeUser();
        Passport::actingAs($user);

        // PATCH a un recurso singleton del usuario autenticado: sin data.id.
        $response = $this->patchJsonApi('/api/v1/account/change-password', [
            'data' => [
                'type'       => 'change-password',
                'attributes' => [
                    'current_password'      => 'OldPass123',
                    'password'              => 'NewPass123',
                    'password_confirmation' => 'NewPass123',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    public function test_cambiar_password_falla_con_password_actual_incorrecta(): void
    {
        Notification::fake();
        $user = $this->makeUser();
        Passport::actingAs($user);

        $response = $this->patchJsonApi('/api/v1/account/change-password', [
            'data' => [
                'type'       => 'change-password',
                'attributes' => [
                    'current_password'      => 'WrongPass123',
                    'password'              => 'NewPass123',
                    'password_confirmation' => 'NewPass123',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
