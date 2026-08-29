<?php

namespace App\Services;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Models\Payment;
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
        private readonly WompiService $wompi,
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

        // El enlace de pago devuelve la transacción y nuestra propia referencia
        // (`idExterno` / `identificadorEnlaceComercio`, con la forma "pago-{id}").
        $transactionId = $event['idTransaccion']
            ?? $event['transaccionCompra']['idTransaccion']
            ?? $event['data']['transaction']['id']
            ?? null;

        $externalId = $event['idExterno']
            ?? $event['transaccionCompra']['idExterno']
            ?? $event['identificadorEnlaceComercio']
            ?? null;

        $linkId = $event['idEnlace'] ?? null;

        // `esAprobada` es el campo de la API; se aceptan también los estados en
        // texto por si el evento llega en el formato antiguo.
        $approved = $event['esAprobada'] ?? $event['transaccionCompra']['esAprobada'] ?? null;
        $status = strtolower((string) ($event['estado'] ?? $event['data']['transaction']['status'] ?? ''));

        $outcome = match (true) {
            $approved === true => 'paid',
            $approved === false => 'failed',
            in_array($status, ['aprobada', 'approved', 'completed'], true) => 'paid',
            in_array($status, ['rechazada', 'declined', 'error', 'failed'], true) => 'failed',
            default => 'ignored',
        };

        $payment = $this->resolveWompiPayment($externalId, $linkId, $transactionId);

        if ($payment === null) {
            Log::warning('Webhook wompi: no se localizó el pago del evento.', [
                'idExterno' => $externalId,
                'idEnlace' => $linkId,
            ]);

            return ['status' => 'ignored', 'reason' => 'payment not found'];
        }

        // El webhook está firmado, pero el importe del evento no es autoritativo:
        // antes de dar por cobrado se confirma contra la API, que es la que sabe
        // cuánto se cobró de verdad y si la transacción fue real.
        if ($outcome === 'paid' && $transactionId) {
            try {
                $result = $this->wompi->getTransaction((string) $transactionId);
            } catch (\Throwable $e) {
                Log::warning('Webhook wompi: no se pudo confirmar la transacción contra la API.', [
                    'idTransaccion' => $transactionId,
                    'error' => $e->getMessage(),
                ]);

                return ['status' => 'ignored', 'reason' => 'confirmation failed'];
            }

            if (! $result['paid'] || round($result['amount'], 2) + 0.009 < round((float) $payment->amount, 2)) {
                Log::warning('Webhook wompi: el evento no coincide con la transacción.', [
                    'idTransaccion' => $transactionId,
                    'pago' => $payment->id,
                ]);

                return ['status' => 'ignored', 'reason' => 'amount mismatch'];
            }
        }

        return $this->applyOutcomeTo($payment, 'wompi', (string) ($transactionId ?: $payment->transaction_reference), $outcome, $event);
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
     * Localiza el pago de un evento de Wompi. Se prueba primero la referencia
     * propia del comercio ("pago-{id}"), que es estable desde que se crea el
     * enlace, y después el id del enlace o el de la transacción.
     */
    private function resolveWompiPayment(?string $externalId, mixed $linkId, ?string $transactionId): ?Payment
    {
        if ($externalId && preg_match('/^pago-(\d+)$/', $externalId, $m)) {
            $payment = Payment::find((int) $m[1]);

            if ($payment && $payment->gateway === 'wompi') {
                return $payment;
            }
        }

        foreach ([$linkId, $transactionId] as $reference) {
            if ($reference !== null && $reference !== '') {
                $payment = $this->payments->findByGatewayReference('wompi', (string) $reference);

                if ($payment !== null) {
                    return $payment;
                }
            }
        }

        return null;
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

        return $this->applyOutcomeTo($payment, $gateway, $reference, $outcome, $event);
    }

    /**
     * Aplica el resultado a un pago ya localizado, de forma idempotente.
     */
    private function applyOutcomeTo(Payment $payment, string $gateway, string $reference, string $outcome, array $event): array
    {
        if ($outcome === 'ignored') {
            return ['status' => 'ignored'];
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
