<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class PaymentService
{
    /**
     * Claves que jamás deben persistirse en `payments.payload` (datos de tarjeta
     * y credenciales que pudieran venir en la respuesta de una pasarela).
     */
    private const REDACTED_KEYS = [
        'card_number', 'numerotarjeta', 'cvv', 'cvc', 'card', 'tarjetacreditodebido',
        'expiration_month', 'expiration_year', 'mesvencimiento', 'aniovencimiento',
        'access_token', 'client_secret', 'authorization',
    ];

    public function create(Model $payable, array $data): Payment
    {
        return Payment::create([
            'payable_type' => $payable::class,
            'payable_id' => $payable->getKey(),
            'gateway' => $data['gateway'],
            'method' => $data['method'] ?? null,
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'] ?? config('app.currency', 'USD'),
            'status' => $data['status'] ?? 'pending',
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'payload' => isset($data['payload']) ? $this->redact($data['payload']) : null,
            'paid_at' => ($data['status'] ?? 'pending') === 'paid' ? now() : null,
        ]);
    }

    public function markPaid(Payment $payment, ?string $transactionReference = null, ?array $payload = null): Payment
    {
        $payment->update([
            'status' => 'paid',
            'transaction_reference' => $transactionReference ?? $payment->transaction_reference,
            'payload' => $payload !== null ? $this->redact($payload) : $payment->payload,
            'paid_at' => now(),
        ]);

        return $payment->refresh();
    }

    public function markFailed(Payment $payment, ?array $payload = null): Payment
    {
        $payment->update([
            'status' => 'failed',
            'payload' => $payload !== null ? $this->redact($payload) : $payment->payload,
        ]);

        return $payment->refresh();
    }

    /**
     * Saldo pendiente de un pagable: su total menos lo efectivamente cobrado.
     * Es la ÚNICA fuente del importe de un pago; nunca se acepta del cliente.
     */
    public function outstandingFor(Model $payable): float
    {
        $paid = Payment::query()
            ->where('payable_type', $payable::class)
            ->where('payable_id', $payable->getKey())
            ->where('status', 'paid')
            ->sum('amount');

        $total = (float) ($payable->total_price ?? 0);

        return round(max($total - (float) $paid, 0), 2);
    }

    /**
     * ¿El pagable está totalmente cubierto por pagos confirmados?
     */
    public function isFullyPaid(Model $payable): bool
    {
        return $this->outstandingFor($payable) <= 0.009;
    }

    /**
     * Elimina datos de tarjeta y credenciales de una estructura antes de persistirla.
     */
    private function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $payload[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }

    /**
     * Localiza un pago por gateway y referencia de transacción externa
     * (order_id de PayPal, payment_intent de Stripe, idTransaccion de Wompi).
     */
    public function findByGatewayReference(string $gateway, string $reference): ?Payment
    {
        return Payment::query()
            ->where('gateway', $gateway)
            ->where('transaction_reference', $reference)
            ->latest()
            ->first();
    }
}
