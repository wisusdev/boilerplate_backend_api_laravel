<?php

namespace Tests\Feature\Travel;

use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\SubscriberWelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\Role;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Módulo de suscriptores a ofertas (leads):
 * alta pública idempotente, baja, correo de bienvenida, interruptor
 * configurable y gestión admin.
 */
class SubscriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'super-admin', 'user'] as $role) {
            Role::findOrCreate($role, 'api');
        }
    }

    private function apiJson(string $method, string $uri, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode($payload));
    }

    private function subscribePayload(array $attributes): array
    {
        return ['data' => ['type' => 'subscribers', 'attributes' => $attributes]];
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'username'   => 'u' . uniqid(),
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => uniqid() . '@example.com',
            'password'   => bcrypt('password123'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_public_can_subscribe_and_welcome_email_is_sent(): void
    {
        Notification::fake();

        $response = $this->apiJson('POST', '/api/v1/subscribers', $this->subscribePayload([
            'email'  => 'lead@example.com',
            'name'   => 'Lead',
            'source' => 'footer',
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('subscribers', [
            'email'  => 'lead@example.com',
            'status' => 'subscribed',
            'source' => 'footer',
        ]);
        Notification::assertSentOnDemand(SubscriberWelcomeNotification::class);
    }

    public function test_subscribe_is_idempotent(): void
    {
        Subscriber::create(['email' => 'dup@example.com', 'status' => 'subscribed']);

        $response = $this->apiJson('POST', '/api/v1/subscribers', $this->subscribePayload([
            'email' => 'dup@example.com',
        ]));

        $response->assertStatus(200);
        $this->assertSame(1, Subscriber::where('email', 'dup@example.com')->count());
    }

    public function test_resubscribe_reactivates_previous_unsubscribed(): void
    {
        Notification::fake();
        Subscriber::create(['email' => 'back@example.com', 'status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        $response = $this->apiJson('POST', '/api/v1/subscribers', $this->subscribePayload([
            'email' => 'back@example.com',
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('subscribers', ['email' => 'back@example.com', 'status' => 'subscribed']);
        Notification::assertSentOnDemand(SubscriberWelcomeNotification::class);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->apiJson('POST', '/api/v1/subscribers', $this->subscribePayload([
            'email' => 'not-an-email',
        ]))->assertStatus(422);
    }

    public function test_public_unsubscribe_sets_status(): void
    {
        Subscriber::create(['email' => 'bye@example.com', 'status' => 'subscribed']);

        $this->postJson('/api/v1/subscribers/unsubscribe', ['email' => 'bye@example.com'])
            ->assertStatus(200);

        $this->assertDatabaseHas('subscribers', ['email' => 'bye@example.com', 'status' => 'unsubscribed']);
    }

    public function test_subscription_is_blocked_when_module_disabled(): void
    {
        Setting::create(['key' => 'app', 'value' => json_encode(['offers_subscription_enabled' => false])]);

        $this->apiJson('POST', '/api/v1/subscribers', $this->subscribePayload([
            'email' => 'blocked@example.com',
        ]))->assertStatus(403);

        $this->assertDatabaseMissing('subscribers', ['email' => 'blocked@example.com']);
    }

    public function test_admin_can_list_update_and_delete(): void
    {
        Passport::actingAs($this->makeUser('admin'));
        $subscriber = Subscriber::create(['email' => 'managed@example.com', 'status' => 'subscribed']);

        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/subscribers')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->apiJson('PATCH', '/api/v1/subscribers/' . $subscriber->id, [
            'data' => ['type' => 'subscribers', 'id' => (string) $subscriber->id, 'attributes' => ['status' => 'unsubscribed']],
        ])->assertOk();
        $this->assertDatabaseHas('subscribers', ['id' => $subscriber->id, 'status' => 'unsubscribed']);

        $this->call('DELETE', '/api/v1/subscribers/' . $subscriber->id, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ])->assertStatus(204);
        $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
    }

    public function test_non_admin_cannot_list_subscribers(): void
    {
        Passport::actingAs($this->makeUser('user'));

        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/subscribers')
            ->assertForbidden();
    }
}
