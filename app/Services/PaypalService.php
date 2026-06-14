<?php

namespace App\Services;

use App\Models\Setting;
use App\Traits\EncryptsCredentials;
use App\Traits\ExternalConsumerServices;

class PaypalService
{
    use EncryptsCredentials;
    use ExternalConsumerServices;

    protected string $base_url;
    protected string $client_id;
    protected string $client_secret;

    public function __construct()
    {
        $pgRow = Setting::where('key', 'payment_gateway')->first();
        $pg    = $pgRow ? json_decode($pgRow->value, true) : [];

        // Support both flat keys (new) and nested payment_methods (legacy seeder)
        $mode               = $pg['paypal_mode'] ?? $pg['payment_methods']['paypal']['mode'] ?? 'sandbox';
        $this->client_id    = $this->decryptCredential($pg['paypal_client_id'] ?? $pg['payment_methods']['paypal']['client_id'] ?? config('services.paypal.client_id', ''));
        $this->client_secret = $this->decryptCredential($pg['paypal_client_secret'] ?? $pg['payment_methods']['paypal']['client_secret'] ?? config('services.paypal.client_secret', ''));
        $this->base_url     = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

	protected function getAccessToken(): string
	{
		// Verificar si el token ya existe en la sesión y aún es válido
		$currentTime = time();
		if (session()->has('paypal_access_token') && session()->has('paypal_token_expires') && $currentTime < session('paypal_token_expires')) {
			return session('paypal_access_token');
		}

		// Solicitar un nuevo token a PayPal
		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/oauth2/token',
			[
				'grant_type' => 'client_credentials',
				'return_unconsented_scopes' => 'true',
			],
			[
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
			]
		);

		$responseBody = json_decode($response);

		if (empty($responseBody->access_token)) {
			$error = $responseBody->error_description ?? $responseBody->error ?? 'PayPal auth failed';
			throw new \RuntimeException("PayPal: {$error}");
		}

		// Guardar el nuevo token y su tiempo de expiración en la sesión
		$expiresIn = $responseBody->expires_in ?? 3600;
		session([
			'paypal_access_token' => $responseBody->access_token,
			'paypal_token_expires' => $currentTime + $expiresIn - 300,
		]);

		return $responseBody->access_token;
	}

    public function createProduct(string $productId, string $name, string $description, string $type = 'SERVICE', string $category = 'SOFTWARE'): object
    {
        $accessToken = $this->getAccessToken();

        $response = $this->makeRequest(
            'POST',
            $this->base_url . '/v1/catalogs/products',
            [
                'id' => $productId,
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'category' => $category,
                'image_url' => 'https://wisus.dev/wp-content/uploads/2022/05/984196.png', // Deberías reemplazar esto con la URL de la imagen de tu producto
                'home_url' => 'https://wisus.dev/wp-content/uploads/2022/05/1053367.png', // Deberías reemplazar esto con la URL de inicio de tu producto
            ],
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            true
        );

        return json_decode($response);
    }

	public function updateProduct(string $productId, string $name, string $description)
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'PATCH',
			$this->base_url . '/v1/catalogs/products/' . $productId,
			[
				[
					'op' => 'replace',
					'path' => '/name',
					'value' => $name,
				],
				[
					'op' => 'replace',
					'path' => '/description',
					'value' => $description,
				]
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		return json_decode($response);
	}

	public function createSubscription(string $subscriptionId, string $planId, string $name, string $email): object
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/billing/subscriptions',
			[
				'plan_id' => $planId,
				'subscriber' => [
					'name' => [
						'given_name' => $name,
					],
					'email_address' => $email
				],
				'application_context' => [
					'brand_name' => config('app.name'), // Deberías reemplazar esto con el nombre de tu marca
					'shipping_preference' => 'NO_SHIPPING', // Puedes cambiar esto a GET_FROM_FILE si deseas obtener la dirección de envío del cliente
					'user_action' => 'SUBSCRIBE_NOW', // Puedes cambiar esto a CONTINUE si deseas que el cliente continúe con la suscripción
					'return_url' => config('app.frontend_url') . '/payment-success/' . $subscriptionId, // Deberías reemplazar esto con la URL de retorno de tu aplicación
					'cancel_url' => config('app.frontend_url') . '/payment-cancelled/' . $subscriptionId, // Deberías reemplazar esto con la URL de cancelación de tu aplicación
				]
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		return json_decode($response);
	}

	public function updateSubscription(string $subscriptionId, string $path, string $value): object
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'PATCH',
			$this->base_url . '/v1/catalogs/subscriptions/' . $subscriptionId,
			[
				'path' => $path,
				'value' => $value,
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		return json_decode($response);
	}

	public function activateSubscription(string $subscriptionId): object
	{
		return $this->updateSubscription($subscriptionId, '/status', 'ACTIVE');
	}

	public function deactivateSubscription(string $subscriptionId): object
	{
		return $this->updateSubscription($subscriptionId, '/status', 'INACTIVE');
	}


    public function createPlan(string $packageId, string $name, string $description, int $intervalCount, string $interval, float $price): object
    {
        $accessToken = $this->getAccessToken();

        $response = $this->makeRequest(
            'POST',
            $this->base_url . '/v1/billing/plans',
            [
				'id' => $packageId,
                'product_id' => $packageId, // Deberías reemplazar esto con el ID de tu producto
                'name' => $name,
                'description' => $description,
                'status' => 'ACTIVE', // Puedes cambiar esto a INACTIVE si deseas crear el plan en estado inactivo
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => strtoupper($interval), // Puedes cambiar esto a DAY, WEEK, MONTH, YEAR
                            'interval_count' => $intervalCount, // Reemplazar esto con tu cantidad de intervalos (1, 2, 3, etc.)
                        ],
                        'tenure_type' => 'REGULAR', // Puedes cambiar esto a TRIAL si deseas crear un plan de prueba
                        'sequence' => 1, // Reemplazar esto con tu secuencia
                        'total_cycles' => 0, // Reemplazar esto con tu cantidad de ciclos
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => $price, // Reemplazar esto con tu precio de plan
                                'currency_code' => 'USD' // Deberías reemplazar esto con tu moneda
                            ]
                        ]
                    ]
                ],
                'payment_preferences' => [ 
                    'auto_bill_outstanding' => true, // True si deseas facturación automática
                    'setup_fee' => [
                        'value' => '0', // Reemplazar esto con tu precio de configuración
                        'currency_code' => 'USD' // Deberías reemplazar esto con tu moneda
                    ],
                    'setup_fee_failure_action' => 'CONTINUE', // Puedes cambiar esto a CANCEL si deseas cancelar la configuración en caso de fallo
                    'payment_failure_threshold' => 3 // Reemplazar esto con tu cantidad de intentos
                ],
                'taxes' => [
                    'percentage' => '0', // Reemplazar esto con tu porcentaje de impuestos
                    'inclusive' => false // Reemplazar esto con true si los impuestos están incluidos en el precio
                ]
            ],
            [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
            ],
			true
        );

        return json_decode($response);
    }

	public function subscriptionDetails(string $subscriptionId): object
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'GET',
			$this->base_url . '/v1/billing/subscriptions/' . $subscriptionId,
			[],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		return json_decode($response);
	}

	public function cancelSubscription(string $subscriptionId, string $reason): object
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/billing/subscriptions/' . $subscriptionId . '/cancel',
			[
				'reason' => $reason
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		return json_decode($response);
	}

	/**
	 * Create a one-time payment Order (v2/checkout/orders).
	 *
	 * @return array{order_id: string, approve_url: string}
	 */
	public function createOrder(float $amount, string $currency, string $returnUrl, string $cancelUrl): array
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v2/checkout/orders',
			[
				'intent'         => 'CAPTURE',
				'purchase_units' => [
					[
						'amount' => [
							'currency_code' => strtoupper($currency),
							'value'         => number_format($amount, 2, '.', ''),
						],
					],
				],
				'application_context' => [
					'return_url' => $returnUrl,
					'cancel_url' => $cancelUrl,
				],
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		$data = json_decode($response, true);
		$approveUrl = '';
		foreach ($data['links'] ?? [] as $link) {
			if ($link['rel'] === 'approve') {
				$approveUrl = $link['href'];
				break;
			}
		}

		return [
			'order_id'    => $data['id'] ?? '',
			'approve_url' => $approveUrl,
		];
	}

	/**
	 * Capture an approved PayPal order.
	 */
	public function captureOrder(string $orderId): array
	{
		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v2/checkout/orders/' . $orderId . '/capture',
			[],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		return json_decode($response, true);
	}

	/**
	 * ID del webhook configurado en el dashboard de PayPal (necesario para verificar firmas).
	 */
	public function getWebhookId(): string
	{
		$pgRow = Setting::where('key', 'payment_gateway')->first();
		$pg    = $pgRow ? json_decode($pgRow->value, true) : [];

		return $this->decryptCredential((string) ($pg['paypal_webhook_id'] ?? config('services.paypal.webhook_id') ?? ''));
	}

	/**
	 * Verifica la firma de un webhook usando la API oficial de PayPal
	 * (/v1/notifications/verify-webhook-signature).
	 *
	 * @param array<string,string> $headers Cabeceras PAYPAL-* de la petición (en minúsculas)
	 * @param array<string,mixed>  $event   Cuerpo del evento decodificado
	 */
	public function verifyWebhookSignature(array $headers, array $event): bool
	{
		$webhookId = $this->getWebhookId();
		if ($webhookId === '') {
			return false;
		}

		$accessToken = $this->getAccessToken();

		$response = $this->makeRequest(
			'POST',
			$this->base_url . '/v1/notifications/verify-webhook-signature',
			[
				'auth_algo'         => $headers['paypal-auth-algo'] ?? '',
				'cert_url'          => $headers['paypal-cert-url'] ?? '',
				'transmission_id'   => $headers['paypal-transmission-id'] ?? '',
				'transmission_sig'  => $headers['paypal-transmission-sig'] ?? '',
				'transmission_time' => $headers['paypal-transmission-time'] ?? '',
				'webhook_id'        => $webhookId,
				'webhook_event'     => $event,
			],
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $accessToken,
			],
			true
		);

		$data = json_decode($response, true);

		return ($data['verification_status'] ?? '') === 'SUCCESS';
	}
}
