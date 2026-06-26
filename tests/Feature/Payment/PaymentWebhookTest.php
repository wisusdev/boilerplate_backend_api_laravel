<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Services\PaypalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los webhooks server-to-server de pago: verificación de firma,
 * marcado de pago e idempotencia. Los webhooks son la fuente de verdad del
 * estado del pago aunque el cliente nunca regrese a la app.
 */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const STRIPE_SECRET = 'whsec_test_secret';
    private const WOMPI_SECRET  = 'wompi_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create([
            'key'   => 'payment_gateway',
            'value' => json_encode([
                'stripe_webhook_secret' => self::STRIPE_SECRET,
                'wompi_webhook_secret'  => self::WOMPI_SECRET,
            ]),
        ]);
    }

    private function makePayment(string $gateway, string $reference, string $status = 'pending'): Payment
    {
        $user = User::create([
            'username'   => 'payer_' . uniqid(),
            'first_name' => 'Pay',
            'last_name'  => 'Er',
            'email'      => uniqid() . '@example.com',
            'password'   => bcrypt('password123'),
        ]);

        $tour = Tour::create([
            'title' => 'Tour', 'description' => 'd', 'price' => 50,
            'max_capacity' => 10, 'location' => 'SS', 'currency_code' => 'USD', 'is_active' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $user->id, 'bookable_type' => Tour::class, 'bookable_id' => $tour->id,
            'starts_at' => '2099-01-01 00:00:00',
            'party_size' => 1, 'total_price' => 50, 'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        return Payment::create([
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'gateway' => $gateway, 'method' => 'card', 'amount' => 50, 'currency_code' => 'USD',
            'status' => $status, 'transaction_reference' => $reference,
        ]);
    }

    private function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    // ─── Stripe ────────────────────────────────────────────────────────────────

    public function test_stripe_webhook_marks_payment_paid_with_valid_signature(): void
    {
        $payment = $this->makePayment('stripe', 'pi_12345');

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_12345']],
        ]);

        $response = $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, self::STRIPE_SECRET),
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);

        $response->assertOk()->assertJson(['received' => true, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        $payment = $this->makePayment('stripe', 'pi_67890');

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_67890']],
        ]);

        $response = $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef',
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_stripe_webhook_rejects_expired_timestamp(): void
    {
        $this->makePayment('stripe', 'pi_old');

        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_old']]]);
        $oldTs   = time() - 1000; // fuera de la tolerancia de 300s

        $response = $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, self::STRIPE_SECRET, $oldTs),
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);

        $response->assertStatus(400);
    }

    public function test_stripe_webhook_is_idempotent(): void
    {
        $payment = $this->makePayment('stripe', 'pi_idem', 'paid');

        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_idem']]]);

        $response = $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, self::STRIPE_SECRET),
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);

        $response->assertOk()->assertJson(['status' => 'already_processed']);
    }

    public function test_stripe_webhook_marks_payment_failed(): void
    {
        $payment = $this->makePayment('stripe', 'pi_fail');

        $payload = json_encode(['type' => 'payment_intent.payment_failed', 'data' => ['object' => ['id' => 'pi_fail']]]);

        $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, self::STRIPE_SECRET),
            'CONTENT_TYPE'          => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'failed']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    }

    public function test_stripe_webhook_ignores_unknown_payment(): void
    {
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_unknown']]]);

        $this->call('POST', '/api/v1/payments/webhook/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, self::STRIPE_SECRET),
            'CONTENT_TYPE'          => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'ignored']);
    }

    // ─── Wompi ───────────────────────────────────────────────────────────────

    public function test_wompi_webhook_marks_payment_paid_with_valid_signature(): void
    {
        $payment = $this->makePayment('wompi', 'WTX_111');

        $payload   = json_encode(['idTransaccion' => 'WTX_111', 'estado' => 'APROBADA']);
        $signature = hash_hmac('sha256', $payload, self::WOMPI_SECRET);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => $signature,
            'CONTENT_TYPE'           => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'paid']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_wompi_webhook_rejects_invalid_signature(): void
    {
        $payment = $this->makePayment('wompi', 'WTX_222');
        $payload = json_encode(['idTransaccion' => 'WTX_222', 'estado' => 'APROBADA']);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => 'wrong_signature',
            'CONTENT_TYPE'           => 'application/json',
        ], $payload)->assertStatus(400);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    // ─── PayPal (firma verificada vía API, mockeada) ─────────────────────────

    public function test_paypal_webhook_marks_payment_paid_when_signature_valid(): void
    {
        $payment = $this->makePayment('paypal', 'ORDER_999');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(true);
        });

        $payload = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER_999']]],
        ]);

        $this->call('POST', '/api/v1/payments/webhook/paypal', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'paid']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_paypal_webhook_rejects_when_signature_invalid(): void
    {
        $payment = $this->makePayment('paypal', 'ORDER_000');

        $this->mock(PaypalService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn(false);
        });

        $payload = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER_000']]],
        ]);

        $this->call('POST', '/api/v1/payments/webhook/paypal', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    // ─── Routing ─────────────────────────────────────────────────────────────

    public function test_unknown_gateway_returns_404(): void
    {
        $this->call('POST', '/api/v1/payments/webhook/bitcoin', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertNotFound();
    }
}
