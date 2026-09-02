<?php

namespace App\Http\Controllers\Api\Travel;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe webhooks server-to-server de las pasarelas de pago.
 *
 * Endpoint público (los gateways no envían Bearer token) protegido por la
 * verificación de firma de cada proveedor. Es la fuente de verdad del estado
 * del pago, independiente de que el cliente regrese o no a la app.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookService $service) {}

    public function handle(string $gateway, Request $request): JsonResponse
    {
        try {
            $result = match ($gateway) {
                'wompi' => $this->service->handleWompi($request),
                default => abort(404),
            };
        } catch (InvalidWebhookSignatureException $e) {
            return response()->json(['received' => false, 'error' => 'invalid signature'], 400);
        }

        return response()->json(['received' => true] + $result);
    }
}
