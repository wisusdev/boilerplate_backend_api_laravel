<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        Passport::tokensExpireIn(now()->addDays(intval(config('auth.life_time_token'))));
        Passport::refreshTokensExpireIn(now()->addDays(intval(config('auth.life_time_refresh_token'))));
        Passport::personalAccessTokensExpireIn(now()->addMonths(intval(config('auth.life_personal_access_token'))));
    }
}
