<?php

namespace App\Providers;

use App\Services\Booking\TourBookingHandler;
use App\Services\Booking\TransportBookingHandler;
use App\Services\BookingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookingService::class, function ($app) {
            $service = new BookingService();
            $service->registerHandler('tour', $app->make(TourBookingHandler::class));
            $service->registerHandler('transport', $app->make(TransportBookingHandler::class));
            return $service;
        });
    }

    public function boot(): void
    {
        //
    }
}
