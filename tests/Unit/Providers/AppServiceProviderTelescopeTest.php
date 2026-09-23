<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Arr;
use Laravel\Telescope\Telescope;
use Tests\TestCase;

class AppServiceProviderTelescopeTest extends TestCase
{
    protected function tearDown(): void
    {
        // No hay una forma pública de "des-registrar" una entrada; se limpia
        // el estado estático a mano para no filtrar hacia otros tests.
        Telescope::$hiddenRequestParameters = [];

        parent::tearDown();
    }

    /**
     * hardenTelescope() es privado y solo actúa cuando telescope.enabled es
     * verdadero; se invoca por reflexión con ese ajuste forzado en vez de
     * depender de arrancar el proveedor completo con Telescope habilitado.
     */
    private function runHardenTelescope(): void
    {
        config(['telescope.enabled' => true]);

        $provider = new AppServiceProvider(app());
        $method = new \ReflectionMethod($provider, 'hardenTelescope');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    public function test_oculta_las_rutas_anidadas_de_los_secretos_de_settings(): void
    {
        $this->runHardenTelescope();

        $esperadas = [
            'data.attributes.wompi_public_key',
            'data.attributes.wompi_private_key',
            'data.attributes.wompi_audience',
            'data.attributes.dte_mh_password',
            'data.attributes.dte_cert_password',
        ];

        foreach ($esperadas as $ruta) {
            $this->assertContains($ruta, Telescope::$hiddenRequestParameters, "Falta ocultar: {$ruta}");
        }
    }

    public function test_el_algoritmo_de_redaccion_de_telescope_enmascara_un_payload_real_de_settings(): void
    {
        // Reproduce exactamente Illuminate\...\RequestWatcher::hideParameters():
        // compara por ruta con puntos, no por nombre de clave en cualquier
        // profundidad. Esto es lo que antes fallaba: los nombres sueltos
        // ('password', 'card_number'...) nunca calzaban con esta forma anidada.
        $this->runHardenTelescope();

        $payload = [
            'data' => [
                'attributes' => [
                    'wompi_private_key' => 'prv_real_secreto',
                    'dte_mh_password' => 'clave-hacienda-real',
                    'dte_cert_password' => 'clave-certificado-real',
                    'app_name' => 'Cusgo Adventures',
                ],
            ],
        ];

        foreach (Telescope::$hiddenRequestParameters as $ruta) {
            if (Arr::get($payload, $ruta)) {
                Arr::set($payload, $ruta, '********');
            }
        }

        $this->assertSame('********', $payload['data']['attributes']['wompi_private_key']);
        $this->assertSame('********', $payload['data']['attributes']['dte_mh_password']);
        $this->assertSame('********', $payload['data']['attributes']['dte_cert_password']);
        $this->assertSame('Cusgo Adventures', $payload['data']['attributes']['app_name']);
    }
}
