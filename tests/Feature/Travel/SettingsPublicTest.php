<?php

namespace Tests\Feature\Travel;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint público de ajustes: responde a visitantes sin sesión (regresión del
 * TypeError con $isAdmin nulo), expone el flag de módulos y oculta credenciales.
 */
class SettingsPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'app', 'value' => json_encode([
            'app_name' => 'Cusgo Adventures',
            'offers_subscription_enabled' => true,
        ])]);

        Setting::create(['key' => 'payment_gateway', 'value' => json_encode([
            'payment_methods' => [
                'stripe' => ['enabled' => true, 'mode' => 'sandbox', 'key' => 'pk_live', 'secret' => 'sk_secret_value'],
            ],
        ])]);
    }

    public function test_public_settings_respond_without_authentication(): void
    {
        $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.attributes.offers_subscription_enabled', true);
    }

    public function test_public_settings_do_not_leak_credentials(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get('/api/v1/settings')
            ->assertOk();

        $attributes = $response->json('data.attributes');

        $this->assertArrayNotHasKey('stripe_secret_key', $attributes);
        $this->assertStringNotContainsString('sk_secret_value', json_encode($attributes));
    }
}
