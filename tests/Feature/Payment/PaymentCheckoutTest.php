<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
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
                    'amount' => 200.00,
                    'currency_code' => 'USD',
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

    public function test_checkout_wompi_crea_pago_pendiente_y_retorna_redirect_url_3ds(): void
    {
        $user = $this->createAuthenticatedUser();
        $booking = $this->createBooking($user);

        $wompiResponse = (object) [
            'idTransaccion' => 'WOMPI_TXN_999',
            'urlCompletarPago3Ds' => 'https://3ds.wompi.sv/verify/WOMPI_TXN_999',
            'monto' => 200.00,
            'esReal' => false,
        ];

        $this->mock(WompiService::class, function ($mock) use ($wompiResponse) {
            $mock->shouldReceive('createPaymentWithCard')
                ->once()
                ->andReturn($wompiResponse);
        });

        $cardData = [
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'expiration_month' => 12,
            'expiration_year' => 27,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'city' => 'San Salvador',
            'address' => 'Calle 1',
            'state' => 'SS',
            'postal_code' => '01101',
            'phone' => '75551234',
        ];

        $response = $this->postJsonApi(
            '/api/v1/payments/checkout',
            $this->checkoutPayload($booking, 'wompi', $cardData),
        );

        $response->assertOk();
        $attrs = $response->json('data.attributes');
        $this->assertEquals('wompi', $attrs['gateway']);
        $this->assertEquals('WOMPI_TXN_999', $attrs['transaction_id']);
        $this->assertStringContainsString('wompi.sv', $attrs['redirect_url']);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'wompi',
            'status' => 'pending',
            'transaction_reference' => 'WOMPI_TXN_999',
        ]);
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

        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);

        $this->postJsonApi('/api/v1/payments/checkout', $this->checkoutPayload($booking, 'bitcoin'));
    }
}
