<?php

namespace App\Services;

use App\Traits\ExternalConsumerServices;
use GuzzleHttp\Exception\GuzzleException;

class PaypalService
{
    use ExternalConsumerServices;
    protected string $base_url;
    protected string $client_id;
    protected string $client_secret;

    public function __construct()
    {
        $this->base_url = config('services.paypal.base_uri');
        $this->client_id = config('services.paypal.client_id');
        $this->client_secret = config('services.paypal.client_secret');
    }

    protected function getAccessToken(): string
    {
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

        return json_decode($response)->access_token;
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


    public function createSubscriptionPlan(int $packageId, string $name, string $description, int $intervalCount, string $interval, float $price): object
    {
        $accessToken = $this->getAccessToken();

        $response = $this->makeRequest(
            'POST',
            $this->base_url . '/v1/billing/plans',
            [],
            [
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
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ]
        );

        return json_decode($response);
    }
}
