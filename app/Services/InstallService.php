<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Instalador al estilo WordPress: crea el primer usuario administrador y
 * guarda los datos básicos del sitio. Solo puede ejecutarse mientras la
 * aplicación no esté instalada (no exista ningún administrador).
 */
class InstallService
{
    /**
     * Símbolos de moneda más comunes; el código se usa como respaldo.
     */
    private const CURRENCY_SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'MXN' => '$',
        'PEN' => 'S/', 'COP' => '$', 'ARS' => '$', 'CLP' => '$',
        'BRL' => 'R$', 'GTQ' => 'Q', 'HNL' => 'L', 'JPY' => '¥',
    ];

    /**
     * La app está instalada cuando ya existe al menos un administrador.
     */
    public function isInstalled(): bool
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))
            ->exists();
    }

    /**
     * Crea el primer administrador y guarda los datos del sitio.
     *
     * @param  array{first_name:string,last_name:string,email:string,password:string,site_name?:string,contact_email?:string,currency?:string,timezone?:string}  $data
     */
    public function install(array $data, bool $force = false): User
    {
        if (! $force && $this->isInstalled()) {
            throw new RuntimeException('app.alreadyInstalled');
        }

        return DB::transaction(function () use ($data) {
            // Garantiza los datos base (permisos, roles, categorías, settings) aunque
            // no se haya ejecutado `db:seed`. Así `migrate` + /install basta para tener
            // una app funcional, y el admin recibe TODOS los permisos.
            $this->ensureBaseDataSeeded();

            $user = User::create([
                'username' => $this->generateUsername($data['email'] ?? $data['first_name'] ?? 'admin'),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ]);

            // Marca el correo como verificado: el admin fundador no necesita verificación.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->ensureAdminRole();
            $user->assignRole('admin');

            $this->storeSiteSettings($data);

            return $user;
        });
    }

    /**
     * Siembra los datos base de forma idempotente (permisos, roles y categorías
     * de gasto). Los settings solo se siembran si la tabla está vacía. Refresca
     * la caché de permisos de Spatie para que surtan efecto de inmediato.
     */
    private function ensureBaseDataSeeded(): void
    {
        if (Setting::query()->count() === 0) {
            (new SettingSeeder)->run();
        }

        (new PermissionSeeder)->run();
        (new RoleSeeder)->run();
        (new ExpenseCategorySeeder)->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Garantiza que exista el rol admin con todos los permisos.
     */
    private function ensureAdminRole(): Role
    {
        $role = Role::findOrCreate('admin', 'api');

        $permissions = Permission::all();
        if ($permissions->isNotEmpty()) {
            $role->givePermissionTo($permissions);
        }

        return $role;
    }

    /**
     * Fusiona los datos del sitio en los settings 'app' y 'payment_gateway'.
     */
    private function storeSiteSettings(array $data): void
    {
        $app = $this->readSetting('app');
        if (! empty($data['site_name'])) {
            $app['name'] = $data['site_name'];
        }
        if (! empty($data['contact_email'])) {
            $app['email'] = $data['contact_email'];
        }
        if (! empty($data['timezone'])) {
            $app['timezone'] = $data['timezone'];
        }
        $app['installed'] = true;
        $app['installed_at'] = now()->toIso8601String();
        Setting::updateOrCreate(['key' => 'app'], ['value' => json_encode($app)]);

        if (! empty($data['currency'])) {
            $code = strtoupper($data['currency']);
            $pg = $this->readSetting('payment_gateway');
            $pg['currency'] = $code;
            $pg['currency_symbol'] = self::CURRENCY_SYMBOLS[$code] ?? $code;
            Setting::updateOrCreate(['key' => 'payment_gateway'], ['value' => json_encode($pg)]);
        }
    }

    private function readSetting(string $key): array
    {
        $row = Setting::where('key', $key)->first();

        return json_decode($row->value ?? '{}', true) ?: [];
    }

    /**
     * Genera un username único a partir del correo o nombre.
     */
    private function generateUsername(string $seed): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', Str::before($seed, '@')));
        $base = $base !== '' ? $base : 'admin';

        $username = $base;
        while (User::query()->where('username', $username)->exists()) {
            $username = $base.random_int(100, 9999);
        }

        return $username;
    }
}
