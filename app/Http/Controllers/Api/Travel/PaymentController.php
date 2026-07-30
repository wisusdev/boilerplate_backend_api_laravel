<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PaypalService;
use App\Services\StripeService;
use App\Services\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaypalService $paypalService,
        private readonly StripeService $stripeService,
        private readonly WompiService $wompiService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $isAdmin = $user->can('payments:view-all');

        $payments = Payment::query()
            ->when(! $isAdmin, fn ($q) => $q->whereHasMorph('payable', [Booking::class], function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }))
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $dateFrom = $request->string('date_from')->toString();
                $query->where(function ($innerQuery) use ($dateFrom) {
                    $innerQuery->whereDate('paid_at', '>=', $dateFrom)
                        ->orWhere(function ($fallbackQuery) use ($dateFrom) {
                            $fallbackQuery->whereNull('paid_at')
                                ->whereDate('created_at', '>=', $dateFrom);
                        });
                });
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $dateTo = $request->string('date_to')->toString();
                $query->where(function ($innerQuery) use ($dateTo) {
                    $innerQuery->whereDate('paid_at', '<=', $dateTo)
                        ->orWhere(function ($fallbackQuery) use ($dateTo) {
                            $fallbackQuery->whereNull('paid_at')
                                ->whereDate('created_at', '<=', $dateTo);
                        });
                });
            })
            ->latest()
            ->sparseFieldset()
            ->jsonPaginate();

        return PaymentResource::collection($payments);
    }

    public function store(PaymentRequest $request): PaymentResource
    {
        $data = $request->validated()['data']['attributes'];

        $payable = Booking::findOrFail($data['payable_id']);
        $this->ensureCanAccessBooking($request, $payable);

        return PaymentResource::make($this->paymentService->create($payable, $data));
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        $this->ensureCanAccessPayment($request, $payment);

        return PaymentResource::make($payment);
    }

    /**
     * POST /api/v1/payments/checkout
     *
     * Initiates an online payment (PayPal / Stripe / Wompi).
     *
     * Common attributes:
     *   gateway        paypal | stripe | wompi
     *   payable_type   booking | transport_booking
     *   payable_id     UUID
     *   amount         float
     *   currency_code  ISO-4217 (default USD)
     *
     * Wompi-specific attributes (card + billing data):
     *   card_number, cvv, expiration_month, expiration_year
     *   first_name, last_name, email, city, address
     *   country (ISO-2, default SV), state, postal_code, phone
     */
    public function checkout(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', []);
        $gateway = $attrs['gateway'] ?? '';
        $amount = (float) ($attrs['amount'] ?? 0);
        $currency = $attrs['currency_code'] ?? 'USD';

        $payableType = $attrs['payable_type'] ?? 'booking';
        $payableId = $attrs['payable_id'] ?? null;

        $payable = Booking::findOrFail($payableId);
        $this->ensureCanAccessBooking($request, $payable);

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $returnUrl = "{$frontendUrl}/payment/result?gateway={$gateway}&payable_type={$payableType}&payable_id={$payableId}";
        $cancelUrl = "{$frontendUrl}/payment/result?gateway={$gateway}&status=cancelled";

        $result = match ($gateway) {
            'paypal' => $this->initPaypal($amount, $currency, $returnUrl, $cancelUrl, $payable),
            'stripe' => $this->initStripe($amount, $currency, $payable),
            'wompi' => $this->initWompi($amount, $currency, $returnUrl, $payable, $attrs),
            default => throw new \InvalidArgumentException("Unsupported gateway: {$gateway}"),
        };

        return response()->json([
            'data' => [
                'type' => 'payment-checkout',
                'id' => $result['payment_id'] ?? null,
                'attributes' => $result,
            ],
        ]);
    }

    /**
     * POST /api/v1/payments/verify
     *
     * Called after the user returns from a gateway redirect or JS confirmation.
     *
     * Attributes:
     *   gateway     paypal | stripe | wompi
     *   payment_id  local Payment UUID
     *   token       PayPal order_id | Stripe paymentIntentId | Wompi transactionId
     */
    public function verify(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', []);
        $gateway = $attrs['gateway'] ?? '';
        $paymentId = $attrs['payment_id'] ?? null;
        $token = $attrs['token'] ?? '';

        $payment = Payment::findOrFail($paymentId);

        $transactionRef = $token;
        $payload = $attrs;
        $success = false;

        if ($gateway === 'paypal') {
            try {
                $capture = $this->paypalService->captureOrder($token);
                $success = ($capture['status'] ?? '') === 'COMPLETED';
                $transactionRef = $capture['id'] ?? $token;
                $payload = $capture;
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        } elseif ($gateway === 'stripe') {
            // Stripe PaymentIntent confirmed client-side via Stripe.js
            $success = true;
        } elseif ($gateway === 'wompi') {
            // After 3DS redirect Wompi returns the transactionId as token
            $success = true;
        }

        if ($success) {
            $this->paymentService->markPaid($payment, $transactionRef, $payload);
        }

        return response()->json([
            'data' => [
                'type' => 'payment-verify',
                'id' => $payment->id,
                'attributes' => [
                    'status' => $success ? 'paid' : 'failed',
                    'transaction_reference' => $transactionRef,
                ],
            ],
        ]);
    }

    // ── private helpers ───────────────────────────────────────────────────────

    private function initPaypal(float $amount, string $currency, string $returnUrl, string $cancelUrl, $payable): array
    {
        $order = $this->paypalService->createOrder($amount, $currency, $returnUrl, $cancelUrl);

        $payment = $this->paymentService->create($payable, [
            'gateway' => 'paypal',
            'method' => 'paypal',
            'amount' => $amount,
            'currency_code' => $currency,
            'status' => 'pending',
            'transaction_reference' => $order['order_id'],
        ]);

        return [
            'payment_id' => $payment->id,
            'gateway' => 'paypal',
            'order_id' => $order['order_id'],
            'approve_url' => $order['approve_url'],
        ];
    }

    private function initStripe(float $amount, string $currency, $payable): array
    {
        $intent = $this->stripeService->createPaymentIntent($amount, $currency);

        $payment = $this->paymentService->create($payable, [
            'gateway' => 'stripe',
            'method' => 'card',
            'amount' => $amount,
            'currency_code' => $currency,
            'status' => 'pending',
            'transaction_reference' => $intent['payment_intent_id'],
        ]);

        return [
            'payment_id' => $payment->id,
            'gateway' => 'stripe',
            'client_secret' => $intent['client_secret'],
            'payment_intent_id' => $intent['payment_intent_id'],
            'public_key' => $this->stripeService->getPublicKey(),
        ];
    }

    /**
     * Wompi El Salvador — server-side 3DS flow.
     * Card + billing info is submitted to our backend, which calls the Wompi API
     * and returns the 3DS redirect URL the user must visit to complete verification.
     */
    private function initWompi(float $amount, string $currency, string $returnUrl, $payable, array $attrs): array
    {
        $cardData = [
            'card_number' => $attrs['card_number'] ?? '',
            'cvv' => $attrs['cvv'] ?? '',
            'expiration_month' => (int) ($attrs['expiration_month'] ?? 0),
            'expiration_year' => (int) ($attrs['expiration_year'] ?? 0),
            'first_name' => $attrs['first_name'] ?? '',
            'last_name' => $attrs['last_name'] ?? '',
            'email' => $attrs['email'] ?? '',
            'city' => $attrs['city'] ?? '',
            'address' => $attrs['address'] ?? '',
            'country' => $attrs['country'] ?? 'SV',
            'state' => $attrs['state'] ?? '',
            'postal_code' => $attrs['postal_code'] ?? '',
            'phone' => $attrs['phone'] ?? '',
            'urlRedirect' => $returnUrl,
        ];

        $response = $this->wompiService->createPaymentWithCard($cardData, $amount);

        // Wompi 3DS response: { idTransaccion, urlCompletarPago3Ds, monto, idExterno, esReal }
        $transactionId = $response->idTransaccion ?? null;
        $redirectUrl = $response->urlCompletarPago3Ds ?? null;

        $payment = $this->paymentService->create($payable, [
            'gateway' => 'wompi',
            'method' => 'card',
            'amount' => $amount,
            'currency_code' => $currency,
            'status' => 'pending',
            'transaction_reference' => (string) $transactionId,
        ]);

        return [
            'payment_id' => $payment->id,
            'gateway' => 'wompi',
            'redirect_url' => $redirectUrl,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * Solo el dueño de la reserva o un administrador pueden operar sobre sus pagos.
     */
    private function ensureCanAccessBooking(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('payments:view-all') || $booking->user_id === $user->id,
            403
        );
    }

    /**
     * Solo el dueño del pago (a través de su reserva) o un administrador pueden verlo.
     */
    private function ensureCanAccessPayment(Request $request, Payment $payment): void
    {
        $user = $request->user();

        if ($user->can('payments:view-all')) {
            return;
        }

        $payable = $payment->payable;
        $owns = $payable instanceof Booking && $payable->user_id === $user->id;

        abort_unless($owns, 403);
    }
}
