<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use App\Services\PaypalService;
use App\Services\StripeService;
use App\Services\WompiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createAuthenticatedUser(): User
    {
        $user = User::create([
            'username' => 'checkout_user',
            'first_name' => 'Checkout',
            'last_name' => 'User',
            'email' => 'checkout@example.com',
            'password' => bcrypt('password123'),
        ]);
        Passport::actingAs($user);

        return $user;
    }

    private function createBooking(User $user): Booking
    {
        $tour = Tour::create([
            'title' => 'Beach Tour',
            'description' => 'Sun & sand',
            'price' => 100,
            'max_capacity' => 8,
            'location' => 'La Libertad',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        return Booking::create([
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'starts_at' => '2026-10-15 00:00:00',
            'party_size' => 2,
            'total_price' => 200,
            'currency_code' => 'USD',
            'status' => Booking::STATUS_PENDING,
            'user_id' => $user->id,
        ]);
    }

    private function checkoutPayload(Booking $booking, string $gateway, array $extra = []): array
    {
        return [
            'data' => [
                'type' => 'payment-checkout',
                'attributes' => array_merge([
                    'gateway' => $gateway,
                    'payable_type' => 'booking',
                    'payable_id' => $booking->id,
                ], $extra),
            ],
        ];
    }

    // ─── PayPal ───────────────────────────────────────────────────────────────

    public function test_checkout_paypal_crea_pago_pendiente_y_retorna_approve_url(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->once()
                ->with(200.00, 'USD', \Mockery::any(), \Mockery::any())
                ->andReturn([
                    'order_id' => 'PAYPAL_ORDER_TEST123',
                    'approve_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_TEST123',
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'paypal'));

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertEquals('paypal', $attrs['gateway']);
        $this->assertEquals('PAYPAL_ORDER_TEST123', $attrs['order_id']);
        $this->assertStringContainsString('sandbox.paypal.com', $attrs['approve_url']);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'paypal',
            'status' => 'pending',
            'transaction_reference' => 'PAYPAL_ORDER_TEST123',
        ]);
    }

    public function test_checkout_paypal_falla_si_el_servicio_lanza_excepcion(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('createOrder')
                ->once()
                ->andThrow(new \RuntimeException('PayPal: invalid_client'));
        });

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'paypal'));
    }

    // ─── Stripe ───────────────────────────────────────────────────────────────

    public function test_checkout_stripe_crea_pago_pendiente_y_retorna_client_secret(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->with(200.00, 'USD')
                ->andReturn([
                    'client_secret' => 'pi_test_secret_abc123',
                    'payment_intent_id' => 'pi_test_abc123',
                ]);
            $mock->shouldReceive('getPublicKey')
                ->once()
                ->andReturn('pk_test_public_key');
        });

        $response = $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'stripe'));

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertEquals('stripe', $attrs['gateway']);
        $this->assertEquals('pi_test_secret_abc123', $attrs['client_secret']);
        $this->assertEquals('pi_test_abc123', $attrs['payment_intent_id']);
        $this->assertEquals('pk_test_public_key', $attrs['public_key']);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'stripe',
            'status' => 'pending',
            'transaction_reference' => 'pi_test_abc123',
        ]);
    }

    // ─── Wompi ────────────────────────────────────────────────────────────────

    public function test_checkout_wompi_crea_un_enlace_de_pago_alojado(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user); // total_price = 200

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('createPaymentLink')
                ->once()
                ->withArgs(function (array $data) {
                    // El importe sale del saldo de la reserva, no del cliente,
                    // y la referencia permite localizar el pago en el webhook.
                    return $data['amount'] === 200.0
                        && str_starts_with($data['reference'], 'pago-')
                        && ! isset($data['card_number']);
                })
                ->andReturn([
                    'link_id' => '55123',
                    'url' => 'https://link.wompi.sv/abc123',
                    'qr_url' => 'https://api.wompi.sv/qr/abc123',
                ]);
        });

        $response = $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'wompi'));

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertSame('https://link.wompi.sv/abc123', $attrs['redirect_url']);

        // La referencia guardada es el id del enlace, con el que luego se consulta.
        $this->assertDatabaseHas('payments', [
            'gateway' => 'wompi',
            'status' => 'pending',
            'amount' => 200.00,
            'transaction_reference' => '55123',
        ]);
    }

    public function test_checkout_wompi_no_acepta_datos_de_tarjeta(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('createPaymentLink')
                ->once()
                ->andReturn(['link_id' => '1', 'url' => 'https://link.wompi.sv/x', 'qr_url' => '']);
        });

        // Aunque el cliente los envíe, no se usan: el formulario es de Wompi.
        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'wompi', [
            'card_number' => '4111111111111111',
            'cvv' => '123',
        ]))->assertOk();

        $pago = Payment::first();
        $this->assertStringNotContainsString('4111', json_encode($pago->payload ?? []));
    }

    public function test_checkout_wompi_marca_el_pago_fallido_si_la_pasarela_falla(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $this->mock(WompiService::class, function ($mock) {
            $mock->shouldReceive('createPaymentLink')
                ->once()
                ->andThrow(new \RuntimeException('Wompi: no se pudo crear el enlace de pago.'));
        });

        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'wompi'))
            ->assertStatus(500);

        // No queda un pago pendiente fantasma que bloquee el saldo.
        $this->assertDatabaseHas('payments', ['gateway' => 'wompi', 'status' => 'failed']);
    }

    // ─── Casos generales ──────────────────────────────────────────────────────

    public function test_checkout_requiere_autenticacion(): void
    {
        $response = $this->postJsonApi('/api/v1/payments/checkout', [
            'data' => ['type' => 'payment-checkout', 'attributes' => ['gateway' => 'paypal']],
        ]);

        $response->assertUnauthorized();
    }

    public function test_checkout_falla_si_booking_no_existe(): void
    {
        $this->createAuthenticatedUser();

        $response = $this->postJsonApi('/api/v1/payments/checkout', [
            'data' => [
                'type' => 'payment-checkout',
                'attributes' => [
                    'gateway' => 'paypal',
                    'payable_type' => 'booking',
                    'payable_id' => 99999,
                    'amount' => 100,
                    'currency_code' => 'USD',
                ],
            ],
        ]);

        $response->assertNotFound();
    }

    public function test_checkout_falla_con_gateway_no_soportado(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'bitcoin'))
            ->assertStatus(422);
    }

    public function test_checkout_ignora_el_importe_enviado_por_el_cliente(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user); // total_price = 200

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                // El importe cobrado sale de la reserva, no del payload.
                ->with(200.0, 'USD')
                ->andReturn(['client_secret' => 'cs_test', 'payment_intent_id' => 'pi_test']);
            $mock->shouldReceive('getPublicKey')->andReturn('pk_test');
        });

        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'stripe', [
            'amount' => 0.01,
            'currency_code' => 'EUR',
        ]))->assertOk();

        $this->assertDatabaseHas('payments', [
            'gateway' => 'stripe',
            'amount' => 200.00,
            'currency_code' => 'USD',
            'status' => 'pending',
        ]);
    }

    public function test_checkout_rechaza_una_reserva_ajena(): void
    {
        $owner = User::create([
            'username' => 'owner_checkout',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'owner_checkout@example.com',
            'password' => bcrypt('password123'),
        ]);
        $booking = $this->createBooking($owner);

        $this->createAuthenticatedUser();

        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'stripe'))
            ->assertForbidden();
    }
}
