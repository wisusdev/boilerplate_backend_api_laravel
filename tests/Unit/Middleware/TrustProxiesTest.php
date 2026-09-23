<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regresión: TrustProxies::$proxies era '*', que en Laravel/Symfony no
 * significa "confía en un rango amplio de proxies" sino "confía en quien sea
 * que esté conectado ahora mismo" — cualquiera que conectara directo al
 * origen (el firewall documentado en DESPLIEGUE-GCP.md permite 0.0.0.0/0) se
 * volvía su propio "proxy de confianza" y podía fijar su propio
 * $request->ip() vía X-Forwarded-For, esquivando cualquier rate limiter por
 * IP. Ahora solo se confía en los rangos publicados de Cloudflare.
 */
class TrustProxiesTest extends TestCase
{
    private function resolveIp(string $remoteAddr, ?string $forwardedFor): string
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', $remoteAddr);

        if ($forwardedFor !== null) {
            $request->headers->set('X-Forwarded-For', $forwardedFor);
        }

        (new TrustProxies)->handle($request, fn ($r) => $r);

        return $request->ip();
    }

    public function test_una_conexion_directa_no_cloudflare_no_puede_fijar_su_propia_ip_via_xff(): void
    {
        // Un atacante conectado directo al origen (IP fuera de los rangos de
        // Cloudflare) no debe poder hacerse pasar por otra IP.
        $ip = $this->resolveIp('203.0.113.7', '198.51.100.99');

        $this->assertSame('203.0.113.7', $ip, 'La IP resuelta debe ser la de la conexión real, no la del header spoofeado.');
    }

    public function test_una_conexion_directa_no_cloudflare_sin_header_tambien_resuelve_a_si_misma(): void
    {
        $ip = $this->resolveIp('203.0.113.8', null);

        $this->assertSame('203.0.113.8', $ip);
    }

    public function test_una_conexion_desde_un_rango_real_de_cloudflare_si_honra_x_forwarded_for(): void
    {
        // 104.16.0.0/13 es uno de los rangos publicados de Cloudflare
        // (https://www.cloudflare.com/ips-v4); el tráfico legítimo que pasa
        // por el edge de Cloudflare debe seguir resolviendo la IP real del
        // visitante.
        $ip = $this->resolveIp('104.16.1.1', '198.51.100.42');

        $this->assertSame('198.51.100.42', $ip);
    }
}
