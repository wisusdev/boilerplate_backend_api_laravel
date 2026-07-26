<?php

namespace App\Services;

use App\Models\Setting;
use App\Traits\EncryptsCredentials;
use App\Traits\ExternalConsumerServices;

class WompiService
{
    use EncryptsCredentials;
    use ExternalConsumerServices;

    protected string $base_url = 'https://api.wompi.sv';

    protected string $base_auth_url = 'https://id.wompi.sv';

    protected string $public_key;

    protected string $private_key;

    protected string $audience;

    public function __construct()
    {
        $pgRow = Setting::where('key', 'payment_gateway')->first();
        $pg = $pgRow ? json_decode($pgRow->value, true) : [];

        // Support both new flat keys and legacy nested structure
        $this->public_key = $this->decryptCredential($pg['wompi_public_key'] ?? $pg['payment_methods']['wompi']['key'] ?? config('services.wompi.public_key', ''));
        $this->private_key = $this->decryptCredential($pg['wompi_private_key'] ?? $pg['payment_methods']['wompi']['secret'] ?? config('services.wompi.private_key', ''));
        $this->audience = $this->decryptCredential($pg['wompi_audience'] ?? config('services.wompi.audience', config('app.url', 'localhost')));
    }

    private function getToken(): object
    {
        $response = $this->makeRequest(
            'POST',
            $this->base_auth_url.'/connect/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->public_key,
                'client_secret' => $this->private_key,
                'audience' => $this->audience,
            ],
            [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );

        return json_decode($response);
    }

    public function getRegion(): object
    {
        $token = $this->getToken();

        $response = $this->makeRequest(
            'GET',
            $this->base_url.'/api/Regiones',
            [],
            [
                'Content-Type: application/json',
                'Authorization: Bearer '.$token->access_token,
            ]
        );

        return json_decode($response);
    }

    public function createPaymentWithCard(array $data, float $monto): object
    {
        $token = $this->getToken();

        $response = $this->makeRequest(
            'POST',
            $this->base_url.'/TransaccionCompra/3DS',
            [
                'tarjetaCreditoDebido' => [
                    'numeroTarjeta' => str_replace(' ', '', $data['card_number']),
                    'cvv' => str_replace(' ', '', $data['cvv']),
                    'mesVencimiento' => $data['expiration_month'],
                    'anioVencimiento' => $data['expiration_year'],
                ],
                'monto' => $monto,
                'urlRedirect' => $data['urlRedirect'] ?? config('app.frontend_url', 'http://localhost:5173'),
                'nombre' => $data['first_name'],
                'apellido' => $data['last_name'],
                'email' => $data['email'],
                'idPais' => $data['country'],
                'idRegion' => $data['state'],
                'ciudad' => $data['city'],
                'direccion' => $data['address'],
                'codigoPostal' => $data['postal_code'],
                'telefono' => $data['phone'],
            ],
            [
                'Content-Type: application/json',
                'Authorization: Bearer '.$token->access_token,
            ],
            true
        );

        return json_decode($response);

    }
}
