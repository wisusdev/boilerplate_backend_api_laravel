<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Antes era '*': con Laravel/Symfony eso NO significa "confía en un rango
     * amplio de proxies", significa "confía en quien sea que esté conectado
     * ahora mismo como si fuera un proxy" (ver
     * Illuminate\Http\Middleware\TrustProxies::setTrustedProxyIpAddressesToTheCallingIp).
     * Como el origen es alcanzable directamente desde Internet (no solo desde
     * Cloudflare — ver DESPLIEGUE-GCP.md, la regla de firewall es 0.0.0.0/0),
     * cualquiera que se conectara directo al origen se volvía, para efectos
     * de X-Forwarded-For, su propio "proxy de confianza": podía fijar su
     * propio $request->ip() y esquivar cualquier rate limiter por IP (login,
     * registro, forgot-password...) rotando el header en cada petición.
     *
     * Los rangos de abajo son los de Cloudflare (única entrada de tráfico
     * documentada, DESPLIEGUE-GCP.md), tomados de
     * https://www.cloudflare.com/ips-v4 y https://www.cloudflare.com/ips-v6 el
     * 2026-09-22. Cloudflare los actualiza rara vez, pero conviene revisarlos
     * si algún día empiezan a aparecer IPs "reales" con pinta de edge de CDN.
     *
     * Esto por sí solo NO basta: nginx también debe sobrescribir (no solo
     * anexar) X-Forwarded-For con la IP real que ve, y el firewall de GCP
     * debería restringirse a estos mismos rangos — ambos pendientes en
     * DESPLIEGUE-GCP.md §16. Sin eso, un atacante que conecte directo al
     * origen simplemente deja de figurar en ninguna de estas listas y su
     * petición no llega enmascarada como proxy — pero tampoco hay forma de
     * que Laravel, sin ese trabajo de infraestructura, distinga esa conexión
     * directa de una legítima que sí pasó por Cloudflare.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
