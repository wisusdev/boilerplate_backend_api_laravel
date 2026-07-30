<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Permission::class => PermissionPolicy::class,
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // superadmin = god mode: pasa cualquier autorización (policies y middleware
        // 'permission'/'role') sin depender de filas de permiso. Devolver null deja
        // que el resto de checks decidan para los demás roles.
        Gate::before(fn (User $user) => $user->hasRole('superadmin') ? true : null);

        Passport::tokensExpireIn(now()->addDays(intval(config('auth.life_time_token'))));
        Passport::refreshTokensExpireIn(now()->addDays(intval(config('auth.life_time_refresh_token'))));
        Passport::personalAccessTokensExpireIn(now()->addMonths(intval(config('auth.life_personal_access_token'))));
    }
}
