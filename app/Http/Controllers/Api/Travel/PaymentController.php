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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * Registra un pago sobre una reserva.
     *
     * El importe se deriva SIEMPRE del saldo pendiente de la reserva y el estado
     * inicial es 'pending'. Solo el back-office ('payments:mark-paid') puede dar
     * un pago por cobrado directamente (efectivo o transferencia ya recibidos);
     * para el cliente, registrar un pago manual es declarar una intención de pago.
     */
    public function store(PaymentRequest $request): PaymentResource
    {
        $data = $request->validated()['data']['attributes'];

        $payable = Booking::findOrFail($data['payable_id']);
        $this->ensureCanAccessBooking($request, $payable);

        $outstanding = $this->paymentService->outstandingFor($payable);

        if ($outstanding <= 0) {
            throw ValidationException::withMessages([
                'data.attributes.payable_id' => ['message.bookingAlreadyPaid'],
            ]);
        }

        $canMarkPaid = $request->user()->can('payments:mark-paid');

        $payment = $this->paymentService->create($payable, [
            'gateway' => $data['gateway'],
            'method' => $data['method'] ?? null,
            'amount' => $outstanding,
            'currency_code' => $payable->currency_code ?: config('app.currency', 'USD'),
            'status' => ($canMarkPaid && ($data['status'] ?? null) === 'paid') ? 'paid' : 'pending',
        ]);

        return PaymentResource::make($payment);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        $this->ensureCanAccessPayment($request, $payment);

        return PaymentResource::make($payment);
    }

    /**
     * POST /api/v1/payments/checkout
     *
     * Inicia un pago en línea.
     *
     * Atributos: gateway (paypal|stripe|wompi), payable_type, payable_id. El
     * importe y la moneda NO se aceptan del cliente: salen del saldo de la reserva.
     *
     * Wompi devuelve `redirect_url`: el enlace alojado donde el cliente introduce
     * su tarjeta. Este servidor nunca recibe datos de tarjeta.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'data.attributes.gateway' => ['required', 'string', Rule::in(['paypal', 'stripe', 'wompi'])],
            'data.attributes.payable_type' => ['sometimes', 'string', Rule::in(['booking'])],
            'data.attributes.payable_id' => ['required', 'integer'],
        ]);

        $attrs = $request->input('data.attributes', []);
        $gateway = $attrs['gateway'];

        $payableType = $attrs['payable_type'] ?? 'booking';
        $payableId = $attrs['payable_id'];

        $payable = Booking::findOrFail($payableId);
        $this->ensureCanAccessBooking($request, $payable);

        // El importe y la moneda NUNCA se aceptan del cliente: se derivan del
        // saldo pendiente de la reserva, calculado en servidor.
        $amount = $this->paymentService->outstandingFor($payable);
        $currency = $payable->currency_code ?: config('app.currency', 'USD');

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'data.attributes.payable_id' => ['message.bookingAlreadyPaid'],
            ]);
        }

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $returnUrl = "{$frontendUrl}/payment/result?gateway={$gateway}&payable_type={$payableType}&payable_id={$payableId}";
        $cancelUrl = "{$frontendUrl}/payment/result?gateway={$gateway}&status=cancelled";

        $result = match ($gateway) {
            'paypal' => $this->initPaypal($amount, $currency, $returnUrl, $cancelUrl, $payable),
            'stripe' => $this->initStripe($amount, $currency, $payable),
            'wompi' => $this->initWompi($amount, $currency, $returnUrl, $payable),
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
        $request->validate([
            'data.attributes.gateway' => ['required', 'string', Rule::in(['paypal', 'stripe', 'wompi'])],
            'data.attributes.payment_id' => ['required', 'integer'],
        ]);

        $attrs = $request->input('data.attributes', []);
        $gateway = $attrs['gateway'];

        $payment = Payment::findOrFail($attrs['payment_id']);
        // Sin esto, cualquier usuario autenticado podía confirmar pagos ajenos
        // enumerando ids (payments.id es autoincremental).
        $this->ensureCanAccessPayment($request, $payment);

        // Idempotencia: un pago ya resuelto no se revalida.
        if ($payment->status === 'paid') {
            return $this->verifyResponse($payment, 'paid');
        }

        if ($gateway !== $payment->gateway) {
            throw ValidationException::withMessages([
                'data.attributes.gateway' => ['message.paymentGatewayMismatch'],
            ]);
        }

        try {
            [$success, $transactionRef, $payload] = match ($gateway) {
                'paypal' => $this->verifyPaypal($payment),
                'stripe' => $this->verifyStripe($payment),
                'wompi' => $this->verifyWompi($payment),
            };
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($success) {
            $this->paymentService->markPaid($payment, $transactionRef, $payload);

            return $this->verifyResponse($payment->refresh(), 'paid');
        }

        // Wompi distingue "aún no ha pagado" de "lo intentó y fue rechazado": si no
        // hay transacción, el enlace sigue abierto y el pago sigue pendiente.
        if ($gateway === 'wompi' && empty($payload['transaction_id'])) {
            return $this->verifyResponse($payment, 'pending');
        }

        return $this->verifyResponse($payment, 'failed');
    }

    /**
     * Captura el pedido de PayPal referenciado por el propio pago (no por el
     * cliente) y comprueba que el importe y la moneda coinciden.
     *
     * @return array{0: bool, 1: string, 2: array<string,mixed>}
     */
    private function verifyPaypal(Payment $payment): array
    {
        $orderId = (string) $payment->transaction_reference;

        if ($orderId === '') {
            throw new \RuntimeException('El pago no tiene una orden de PayPal asociada.');
        }

        $capture = $this->paypalService->captureOrder($orderId);

        if (($capture['status'] ?? '') !== 'COMPLETED') {
            return [false, $orderId, $capture];
        }

        // El pedido capturado debe ser exactamente el que se creó para este pago.
        if ((string) ($capture['id'] ?? '') !== $orderId) {
            throw new \RuntimeException('La orden capturada no corresponde a este pago.');
        }

        $unit = $capture['purchase_units'][0] ?? [];
        $captured = $unit['payments']['captures'][0]['amount']
            ?? $unit['amount']
            ?? [];

        $this->assertAmountMatches(
            $payment,
            (float) ($captured['value'] ?? 0),
            strtoupper((string) ($captured['currency_code'] ?? ''))
        );

        return [true, $orderId, $capture];
    }

    /**
     * Recupera el PaymentIntent en el servidor. La confirmación de Stripe.js
     * ocurre en el navegador y por sí sola no prueba nada.
     *
     * @return array{0: bool, 1: string, 2: array<string,mixed>}
     */
    private function verifyStripe(Payment $payment): array
    {
        $intentId = (string) $payment->transaction_reference;

        if ($intentId === '') {
            throw new \RuntimeException('El pago no tiene un PaymentIntent asociado.');
        }

        $intent = $this->stripeService->retrievePaymentIntent($intentId);

        if ($intent['status'] !== 'succeeded') {
            return [false, $intentId, $intent];
        }

        $expectedCents = $this->stripeService->toCents((float) $payment->amount);

        if ($intent['amount_received'] < $expectedCents) {
            throw new \RuntimeException('El importe cobrado no coincide con el pago.');
        }

        $this->assertCurrencyMatches($payment, $intent['currency']);

        return [true, $intentId, $intent];
    }

    /**
     * Consulta el enlace de pago en Wompi. La confirmación no depende de que el
     * cliente vuelva a la aplicación: el webhook firmado hace lo mismo por su
     * cuenta si el navegador se cierra.
     *
     * @return array{0: bool, 1: string, 2: array<string,mixed>}
     */
    private function verifyWompi(Payment $payment): array
    {
        $linkId = (string) $payment->transaction_reference;

        if ($linkId === '') {
            throw new \RuntimeException('El pago no tiene un enlace de Wompi asociado.');
        }

        $result = $this->wompiService->getPaymentLinkResult($linkId);

        if (! $result['paid']) {
            return [false, $linkId, $result];
        }

        $this->assertAmountMatches($payment, $result['amount'], (string) $payment->currency_code);

        // A partir de aquí la referencia es la de la transacción real, que es la
        // que llega en el webhook.
        return [true, $result['transaction_id'] ?: $linkId, $result];
    }

    /**
     * El importe cobrado en la pasarela debe cubrir el pago local.
     */
    private function assertAmountMatches(Payment $payment, float $amount, string $currency): void
    {
        if (round($amount, 2) + 0.009 < round((float) $payment->amount, 2)) {
            throw new \RuntimeException('El importe cobrado no coincide con el pago.');
        }

        $this->assertCurrencyMatches($payment, $currency);
    }

    private function assertCurrencyMatches(Payment $payment, string $currency): void
    {
        if ($currency !== '' && $currency !== strtoupper((string) $payment->currency_code)) {
            throw new \RuntimeException('La moneda cobrada no coincide con el pago.');
        }
    }

    private function verifyResponse(Payment $payment, string $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'payment-verify',
                'id' => $payment->id,
                'attributes' => [
                    'status' => $status,
                    'transaction_reference' => $payment->transaction_reference,
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
     * Wompi El Salvador — enlace de pago alojado.
     *
     * Se crea un enlace en Wompi y se redirige allí al cliente: el formulario de
     * tarjeta es de Wompi, así que el PAN y el CVV nunca tocan este servidor.
     */
    private function initWompi(float $amount, string $currency, string $returnUrl, $payable): array
    {
        // El enlace de Wompi no lleva moneda: Wompi El Salvador liquida en USD.
        // Enviar el importe de otra divisa lo cobraría como si fueran dólares.
        if (strtoupper($currency) !== 'USD') {
            throw ValidationException::withMessages([
                'data.attributes.gateway' => ['Wompi solo admite cobros en USD; esta reserva está en '.$currency.'.'],
            ]);
        }

        // Un reintento no debe dejar pagos pendientes acumulados: si ya hay uno
        // sin resolver para esta reserva, se reutiliza.
        $payment = Payment::query()
            ->where('payable_type', $payable::class)
            ->where('payable_id', $payable->getKey())
            ->where('gateway', 'wompi')
            ->where('status', 'pending')
            ->latest()
            ->first();

        $payment
            ? $payment->update(['amount' => $amount, 'currency_code' => $currency])
            : $payment = $this->paymentService->create($payable, [
                'gateway' => 'wompi',
                'method' => 'card',
                'amount' => $amount,
                'currency_code' => $currency,
                'status' => 'pending',
            ]);

        try {
            $link = $this->wompiService->createPaymentLink([
                // Referencia única del comercio: permite localizar el pago cuando
                // Wompi devuelve el resultado.
                'reference' => 'pago-'.$payment->id,
                'amount' => $amount,
                'product' => $this->payableTitle($payable),
                'description' => 'Reserva #'.$payable->getKey().' · '.$this->payableTitle($payable),
                'redirect_url' => $returnUrl.'&payment_id='.$payment->id,
                'webhook_url' => route('api.v1.payments.webhook', ['gateway' => 'wompi']),
                'return_url' => config('app.frontend_url'),
                'extra' => [
                    'payment_id' => (string) $payment->id,
                    'booking_id' => (string) $payable->getKey(),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->paymentService->markFailed($payment, ['error' => $e->getMessage()]);

            throw $e;
        }

        // El id del enlace es la referencia con la que se consulta el resultado.
        $payment->update(['transaction_reference' => $link['link_id']]);

        return [
            'payment_id' => $payment->id,
            'gateway' => 'wompi',
            'redirect_url' => $link['url'],
            'qr_url' => $link['qr_url'],
        ];
    }

    private function payableTitle($payable): string
    {
        return (string) ($payable->bookable?->title ?? ('Reserva #'.$payable->getKey()));
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
