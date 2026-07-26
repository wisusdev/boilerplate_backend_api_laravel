<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // La caché estática de SiteSettings persiste entre tests del mismo proceso;
        // se reinicia para que cada test lea sus propios settings.
        \App\Support\SiteSettings::flush();
    }

    /**
     * Realiza una petición POST con cabeceras JSON:API (application/vnd.api+json),
     * requeridas por el middleware ValidateJsonApiHeaders.
     */
    protected function postJsonApi(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', $uri, [], [], [], [
            'HTTP_ACCEPT'  => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode($data));
    }
}
