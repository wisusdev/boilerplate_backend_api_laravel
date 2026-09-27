<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas de la API.
 *
 * Importa especialmente `X-Content-Type-Options`: los ficheros subidos se sirven
 * desde el mismo origen (public/storage), y sin ella el navegador puede
 * interpretar como HTML algo que se guardó como imagen.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        // La API solo devuelve JSON y ficheros; nada debe ejecutarse desde aquí.
        // Las rutas /seo/* sirven el HTML del sitio público (la SPA), que sí
        // necesita ejecutar sus scripts: sus cabeceras las pone el nginx del sitio.
        if (! $request->routeIs('seo.*')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'none'; frame-ancestors 'none'; sandbox"
            );
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
