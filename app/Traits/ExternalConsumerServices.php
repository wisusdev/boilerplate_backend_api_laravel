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
     * @param string $requestUri
     * @param array $body
     * @param array $header
     * @return string
     */
    public function makeRequest(string $method, string $requestUri, array $body = [], array $header = [], $isJson = false): string
    {
        $curl = curl_init();

        $curlOptions = [
            CURLOPT_URL => $requestUri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $isJson ? json_encode($body) : http_build_query($body),
            CURLOPT_HTTPHEADER => $header,
        ];

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }
}
