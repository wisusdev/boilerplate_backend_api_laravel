<?php

namespace Tests\Feature\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\User;
use App\Services\WompiService;
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

    private const WOMPI_SECRET = 'wompi_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create([
            'key' => 'payment_gateway',
            'value' => json_encode([
                'wompi_webhook_secret' => self::WOMPI_SECRET,
            ]),
        ]);
    }

    private function makePayment(string $gateway, string $reference, string $status = 'pending'): Payment
    {
        $user = User::create([
            'username' => 'payer_'.uniqid(),
            'first_name' => 'Pay',
            'last_name' => 'Er',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password123'),
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

    // ─── Wompi ───────────────────────────────────────────────────────────────

    /**
     * El webhook confirma la transacción contra la API antes de dar por cobrado.
     */
    private function mockWompiTransaction(string $id, bool $paid, float $amount): void
    {
        $this->mock(WompiService::class, function ($mock) use ($id, $paid, $amount) {
            $mock->shouldReceive('getTransaction')
                ->with($id)
                ->andReturn([
                    'paid' => $paid, 'real' => true, 'amount' => $amount,
                    'transaction_id' => $id, 'external_id' => null, 'message' => null,
                ]);
        });
    }

    public function test_wompi_webhook_marks_payment_paid_with_valid_signature(): void
    {
        $payment = $this->makePayment('wompi', 'WTX_111');
        $this->mockWompiTransaction('WTX_111', true, (float) $payment->amount);

        $payload = json_encode(['idTransaccion' => 'WTX_111', 'estado' => 'APROBADA']);
        $signature = hash_hmac('sha256', $payload, self::WOMPI_SECRET);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'paid']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_wompi_webhook_localiza_el_pago_por_la_referencia_del_comercio(): void
    {
        // El enlace de pago guarda su idEnlace; el evento trae el id de la
        // transacción (distinto) y nuestra referencia en idExterno.
        $payment = $this->makePayment('wompi', '55123');
        $this->mockWompiTransaction('WOMPI-TXN-999', true, (float) $payment->amount);

        $payload = json_encode([
            'idEnlace' => 55123,
            'idExterno' => 'pago-'.$payment->id,
            'idTransaccion' => 'WOMPI-TXN-999',
            'esAprobada' => true,
            'monto' => 100,
        ]);
        $signature = hash_hmac('sha256', $payload, self::WOMPI_SECRET);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'paid']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id, 'status' => 'paid', 'transaction_reference' => 'WOMPI-TXN-999',
        ]);
    }

    public function test_wompi_webhook_ignora_un_evento_cuyo_importe_no_cuadra(): void
    {
        $payment = $this->makePayment('wompi', '55199');
        // El evento dice aprobada, pero en la pasarela solo se cobró 1.00.
        $this->mockWompiTransaction('WOMPI-TXN-BARATA', true, 1.00);

        $payload = json_encode([
            'idExterno' => 'pago-'.$payment->id,
            'idTransaccion' => 'WOMPI-TXN-BARATA',
            'esAprobada' => true,
            'monto' => 9999,
        ]);
        $signature = hash_hmac('sha256', $payload, self::WOMPI_SECRET);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    /**
     * Regresión: un evento firmado que dice "aprobada" pero no trae ningún id
     * de transacción no se puede reconfirmar contra la API — antes ese caso
     * saltaba por completo la reconfirmación y marcaba el pago pagado
     * confiando solo en la firma HMAC y el campo esAprobada del propio evento.
     */
    public function test_wompi_webhook_ignora_un_evento_aprobado_sin_id_de_transaccion(): void
    {
        $payment = $this->makePayment('wompi', '55200');

        $payload = json_encode([
            'idExterno' => 'pago-'.$payment->id,
            'esAprobada' => true,
        ]);
        $signature = hash_hmac('sha256', $payload, self::WOMPI_SECRET);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'missing transaction id']);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_wompi_webhook_marca_fallido_cuando_no_fue_aprobada(): void
    {
        $payment = $this->makePayment('wompi', '55124');

        $payload = json_encode([
            'idExterno' => 'pago-'.$payment->id,
            'idTransaccion' => 'WOMPI-TXN-NO',
            'esAprobada' => false,
        ]);
        $signature = hash_hmac('sha256', $payload, self::WOMPI_SECRET);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    }

    public function test_wompi_webhook_rejects_invalid_signature(): void
    {
        $payment = $this->makePayment('wompi', 'WTX_222');
        $payload = json_encode(['idTransaccion' => 'WTX_222', 'estado' => 'APROBADA']);

        $this->call('POST', '/api/v1/payments/webhook/wompi', [], [], [], [
            'HTTP_X_EVENT_SIGNATURE' => 'wrong_signature',
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

    public function test_stripe_y_paypal_ya_no_son_gateways_de_webhook_validos(): void
    {
        // Retirados del producto: la ruta ya no los reconoce, ni siquiera para
        // rechazarlos "amablemente" — son 404 como cualquier gateway inventado.
        foreach (['stripe', 'paypal'] as $gateway) {
            $this->call('POST', "/api/v1/payments/webhook/{$gateway}", [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], '{}')->assertNotFound();
        }
    }
}
