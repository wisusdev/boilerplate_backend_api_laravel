<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Observers\BookableObserver;
use App\Observers\BookingObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        Booking::observe(BookingObserver::class);
        User::observe(UserObserver::class);

        // Integridad de las relaciones polimórficas (bookings / product_reviews),
        // que no pueden expresarse como clave foránea.
        Tour::observe(BookableObserver::class);
        TransportVehicle::observe(BookableObserver::class);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
