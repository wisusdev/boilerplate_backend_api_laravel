<?php

namespace App\Traits;

trait ExternalConsumerServices
{
    /** Segundos máximos de espera a una pasarela antes de abortar. */
    private const REQUEST_TIMEOUT = 30;

    /**
     * Make a request to an external service
     */
    public function makeRequest(string $method, string $requestUri, array $body = [], array $header = [], bool $isJson = false): string
    {
        $curl = curl_init();

        $curlOptions = [
            CURLOPT_URL => $requestUri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            // Sin redirecciones: un 302 reenviaría la cabecera Authorization
            // (token OAuth de la pasarela) al destino que indique el servidor.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $header,
        ];

        // GET/DELETE sin cuerpo: evita enviar un body vacío que algunas APIs rechazan.
        if ($body !== [] || ! in_array(strtoupper($method), ['GET', 'DELETE'], true)) {
            $curlOptions[CURLOPT_POSTFIELDS] = $isJson ? json_encode($body) : http_build_query($body);
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $curlInfo = curl_getinfo($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            logs()->warning('Fallo de red hacia una pasarela', [
                'host' => parse_url($requestUri, PHP_URL_HOST),
                'error' => $curlError,
            ]);

            throw new \RuntimeException('No se pudo contactar con la pasarela de pago.');
        }

        $responseArray = json_decode($response, true);
        $responseArray = is_array($responseArray) ? $responseArray : [];
        $responseArray['http_code'] = $curlInfo['http_code'];

        // Solo metadatos: el cuerpo de estas respuestas contiene tokens OAuth y,
        // en el caso de Wompi, datos del titular de la tarjeta.
        logs()->info('gateway response', [
            'host' => parse_url($requestUri, PHP_URL_HOST),
            'path' => parse_url($requestUri, PHP_URL_PATH),
            'http_code' => $curlInfo['http_code'],
        ]);

        return json_encode($responseArray);
    }
}
