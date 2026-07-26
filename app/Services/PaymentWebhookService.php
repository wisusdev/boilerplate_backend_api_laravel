<?php

namespace App\Services;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Setting;
use App\Traits\EncryptsCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Procesa webhooks server-to-server de las pasarelas de pago.
 *
 * Los webhooks son la **fuente de verdad** del estado del pago: si el cliente
 * cierra el navegador tras pagar, el endpoint /verify nunca se llama, pero el
 * gateway sí notifica por webhook. Cada handler:
 *   1. Verifica la firma del webhook (rechaza con 400 si es inválida).
 *   2. Localiza el Payment local por su referencia de transacción.
 *   3. Marca el pago como paid/failed de forma idempotente.
 */
class PaymentWebhookService
{
    use EncryptsCredentials;

    /** Tolerancia (segundos) para el timestamp de la firma de Stripe. */
    private const STRIPE_TOLERANCE = 300;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaypalService $paypal,
    ) {}

    // ─── Stripe ────────────────────────────────────────────────────────────────

    public function handleStripe(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret = $this->gatewaySetting('stripe_webhook_secret', 'services.stripe.webhook_secret');

        $this->verifyStripeSignature($payload, $signature, $secret);

        $event = json_decode($payload, true) ?: [];
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];
        $intentId = $object['id'] ?? null;

        if ($intentId === null) {
            return ['status' => 'ignored', 'reason' => 'missing payment intent'];
        }

        return $this->applyOutcome('stripe', $intentId, match ($type) {
            'payment_intent.succeeded' => 'paid',
            'payment_intent.payment_failed' => 'failed',
            default => 'ignored',
        }, $event);
    }

    private function verifyStripeSignature(string $payload, string $signature, string $secret): void
    {
        if ($secret === '' || $signature === '') {
            throw new InvalidWebhookSignatureException('Missing Stripe signature or secret.');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new InvalidWebhookSignatureException('Malformed Stripe signature header.');
        }

        if (abs(time() - (int) $timestamp) > self::STRIPE_TOLERANCE) {
            throw new InvalidWebhookSignatureException('Stripe signature timestamp outside tolerance.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new InvalidWebhookSignatureException('Stripe signature mismatch.');
    }

    // ─── Wompi ───────────────────────────────────────────────────────────────

    public function handleWompi(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Event-Signature', '');
        $secret = $this->gatewaySetting('wompi_webhook_secret', 'services.wompi.webhook_secret');

        $this->verifyHmacSignature($payload, $signature, $secret, 'Wompi');

        $event = json_decode($payload, true) ?: [];
        // Wompi El Salvador entrega el id de transacción y el estado del evento.
        $transactionId = $event['idTransaccion']
            ?? $event['data']['transaction']['id']
            ?? null;
        $status = strtolower((string) ($event['estado'] ?? $event['data']['transaction']['status'] ?? ''));

        if ($transactionId === null) {
            return ['status' => 'ignored', 'reason' => 'missing transaction id'];
        }

        $outcome = match (true) {
            in_array($status, ['aprobada', 'approved', 'completed'], true) => 'paid',
            in_array($status, ['rechazada', 'declined', 'error', 'failed'], true) => 'failed',
            default => 'ignored',
        };

        return $this->applyOutcome('wompi', (string) $transactionId, $outcome, $event);
    }

    // ─── PayPal ──────────────────────────────────────────────────────────────

    public function handlePaypal(Request $request): array
    {
        $event = $request->json()->all();

        // Cabeceras PAYPAL-* normalizadas a minúsculas para la verificación.
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[strtolower($key)] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        if (! $this->paypal->verifyWebhookSignature($headers, $event)) {
            throw new InvalidWebhookSignatureException('PayPal webhook verification failed.');
        }

        $type = $event['event_type'] ?? '';
        $resource = $event['resource'] ?? [];
        // El order_id (referencia guardada al iniciar el checkout) viaja en supplementary_data.
        $orderId = $resource['supplementary_data']['related_ids']['order_id']
            ?? $resource['id']
            ?? null;

        if ($orderId === null) {
            return ['status' => 'ignored', 'reason' => 'missing order id'];
        }

        $outcome = match ($type) {
            'PAYMENT.CAPTURE.COMPLETED' => 'paid',
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => 'failed',
            default => 'ignored',
        };

        return $this->applyOutcome('paypal', (string) $orderId, $outcome, $event);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function verifyHmacSignature(string $payload, string $signature, string $secret, string $gateway): void
    {
        if ($secret === '' || $signature === '') {
            throw new InvalidWebhookSignatureException("Missing {$gateway} signature or secret.");
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException("{$gateway} signature mismatch.");
        }
    }

    /**
     * Aplica el resultado al pago localizado por referencia, de forma idempotente.
     */
    private function applyOutcome(string $gateway, string $reference, string $outcome, array $event): array
    {
        if ($outcome === 'ignored') {
            return ['status' => 'ignored'];
        }

        $payment = $this->payments->findByGatewayReference($gateway, $reference);

        if ($payment === null) {
            Log::warning("Webhook {$gateway}: pago no encontrado para referencia {$reference}.");

            return ['status' => 'ignored', 'reason' => 'payment not found'];
        }

        // Idempotencia: no reprocesar un pago ya resuelto.
        if ($payment->status === 'paid') {
            return ['status' => 'already_processed', 'payment_id' => $payment->id];
        }

        if ($outcome === 'paid') {
            $this->payments->markPaid($payment, $reference, $event);
        } else {
            $this->payments->markFailed($payment, $event);
        }

        return ['status' => $outcome, 'payment_id' => $payment->id];
    }

    private function gatewaySetting(string $key, string $configFallback): string
    {
        $pgRow = Setting::where('key', 'payment_gateway')->first();
        $pg = $pgRow ? json_decode($pgRow->value, true) : [];

        return $this->decryptCredential((string) ($pg[$key] ?? config($configFallback) ?? ''));
    }
}
