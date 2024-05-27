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
        $this->base_url = config('services.paypal.base_url');
        $this->client_id = config('services.paypal.client_id');
        $this->client_secret = config('services.paypal.client_secret');
    }

    /**
     * @throws GuzzleException
     */
    protected function getAccessToken(): string
    {
        $response = $this->makeRequest(
            'POST',
            $this->base_url . '/v1/oauth2/token',
            [],
            ['grant_type' => 'client_credentials',],
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
            ]
        );

        return json_decode($response)->access_token;
    }
}
