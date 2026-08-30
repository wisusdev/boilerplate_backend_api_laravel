<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Origenes explicitos: con '*' cualquier sitio podia consumir la API desde
    // el navegador de la victima. Se configuran por entorno con CORS_ALLOWED_ORIGINS
    // (lista separada por comas).
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_FRONT_URL', 'http://localhost:5173')))
    ))),

    /*
     * En desarrollo el servidor de Vite cambia de puerto cuando el habitual está
     * ocupado (5173 → 5174…), y con la lista fija el navegador bloqueaba todas
     * las llamadas con un "Failed to fetch". En producción esto queda vacío: los
     * orígenes se declaran uno a uno en CORS_ALLOWED_ORIGINS.
     */
    'allowed_origins_patterns' => env('APP_ENV') === 'production'
        ? []
        : ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
