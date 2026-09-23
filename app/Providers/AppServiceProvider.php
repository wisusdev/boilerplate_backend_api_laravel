<?php

namespace App\Providers;

use App\Services\Booking\TourBookingHandler;
use App\Services\Booking\TransportBookingHandler;
use App\Services\BookingService;
use App\Services\CouponService;
use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Telescope;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookingService::class, function ($app) {
            $service = new BookingService($app->make(CouponService::class), $app->make(PaymentService::class));
            $service->registerHandler('tour', $app->make(TourBookingHandler::class));
            $service->registerHandler('transport', $app->make(TransportBookingHandler::class));

            return $service;
        });
    }

    public function boot(): void
    {
        $this->hardenTelescope();
    }

    /**
     * Telescope guarda el cuerpo íntegro de cada petición en la base de datos.
     * Está desactivado por defecto (config/telescope.php); si se habilita para
     * depurar, al menos que no persista contraseñas ni datos de tarjeta.
     *
     * Telescope::hideRequestParameters() compara por ruta EXACTA con notación
     * de puntos (Arr::get/Arr::set), no por nombre de clave en cualquier nivel.
     * Los nombres sueltos de abajo (password, card_number...) solo cubren un
     * payload plano; el body real de PATCH /settings es anidado
     * (data.attributes.wompi_private_key, ...), así que sin las rutas
     * explícitas esos secretos quedaban en claro en telescope_entries pese a
     * que SettingsController los trata como "nunca en claro, ni para un
     * admin" (ver SettingsController::SECRET_KEYS).
     */
    private function hardenTelescope(): void
    {
        if (! class_exists(Telescope::class) || ! config('telescope.enabled')) {
            return;
        }

        Telescope::hideRequestParameters([
            '_token',
            'password', 'password_confirmation', 'current_password',
            'card_number', 'cvv', 'expiration_month', 'expiration_year',
            'access_token', 'id_token', 'token',
            'data.attributes.wompi_public_key',
            'data.attributes.wompi_private_key',
            'data.attributes.wompi_audience',
            'data.attributes.dte_mh_password',
            'data.attributes.dte_cert_password',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'stripe-signature',
            'x-event-signature',
        ]);
    }
}
