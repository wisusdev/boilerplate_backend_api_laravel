<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class PaymentService
{
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
            'payload' => $data['payload'] ?? null,
            'paid_at' => ($data['status'] ?? 'pending') === 'paid' ? now() : null,
        ]);
    }

    public function markPaid(Payment $payment, ?string $transactionReference = null, ?array $payload = null): Payment
    {
        $payment->update([
            'status' => 'paid',
            'transaction_reference' => $transactionReference ?? $payment->transaction_reference,
            'payload' => $payload ?? $payment->payload,
            'paid_at' => now(),
        ]);

        return $payment->refresh();
    }

    public function markFailed(Payment $payment, ?array $payload = null): Payment
    {
        $payment->update([
            'status' => 'failed',
            'payload' => $payload ?? $payment->payload,
        ]);

        return $payment->refresh();
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
