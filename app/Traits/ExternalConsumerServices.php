<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait ExternalConsumerServices
{
    /**
     * Make a request to an external service
     *
     * @param string $method
     * @param string $requestUrl
     * @param array $queryParams
     * @param array $formParams
     * @param array $headers
     * @param bool $isJsonRequest
     * @return string
     * @throws GuzzleException
     */
    public function makeRequest(string $method, string $requestUrl, array $queryParams = [], array $formParams = [], array $headers = [], bool $isJsonRequest = false): string
    {
        $client = new Client();

        $response = $client->request($method, $requestUrl, [
            'query' => $queryParams,
            'form_params' => $formParams,
            'headers' => $headers,
            'json' => $isJsonRequest,
        ]);

        return $response->getBody()->getContents();
    }
}
