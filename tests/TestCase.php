<?php

namespace Tests;

use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // La caché estática de SiteSettings persiste entre tests del mismo proceso;
        // se reinicia para que cada test lea sus propios settings.
        SiteSettings::flush();
    }

    /**
     * Realiza una petición POST con cabeceras JSON:API (application/vnd.api+json),
     * requeridas por el middleware ValidateJsonApiHeaders.
     */
    protected function postJsonApi(string $uri, array $data = []): TestResponse
    {
        return $this->call('POST', $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], json_encode($data));
    }
}
