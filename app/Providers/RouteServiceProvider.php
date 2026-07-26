<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Endpoints de autenticación: límites estrictos por IP para mitigar
        // fuerza bruta y envío masivo de correos. Sin límite en pruebas.
        RateLimiter::for('auth', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(6)->by($request->ip());
        });

        // Registro: además del límite por minuto, un tope por hora para
        // evitar la creación automatizada de cuentas.
        RateLimiter::for('auth-register', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        // Olvidé mi contraseña — envía correos, límite estricto por IP (anti-spam).
        RateLimiter::for('auth-forgot', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Reenvío de verificación de correo — muy limitado para evitar spam de emails.
        RateLimiter::for('email-resend', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(2)->by($key),
                Limit::perHour(6)->by($key),
            ];
        });

        // Formularios públicos (p. ej. contacto / consultas) — anti-spam por IP.
        RateLimiter::for('forms', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perMinute(8)->by($request->ip()),
                Limit::perHour(40)->by($request->ip()),
            ];
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
