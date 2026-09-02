<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->delete();

        $settings = [
            ['key' => 'app', 'value' => json_encode([
                'app_name' => 'Cusgo Adventures',
                'name' => config('app.name'),
                'url_api' => config('app.url'),
                'url_frontend' => config('app.frontend_url'),
                'description' => 'Laravel api, Angular frontend and Flutter mobile.',
                'logo' => '',
                'favicon' => '',
                'email' => 'info@wisus.dev',
                'phone' => '+503 1234-5678',
                'address' => '1234 Main St, San Salvador, El Salvador',
                'timezone' => 'America/El_Salvador',
                'offers_subscription_enabled' => true,
                // Política de reserva GLOBAL (aplica a todos los tours y vehículos).
                'booking_min_advance_days' => 0,
                'booking_cancellation_hours' => 24,
                // Lo pone en true el instalador (wizard /install o `php artisan app:install`).
                'installed' => false,
            ])],

            ['key' => 'payment_gateway', 'value' => json_encode([
                // Moneda GLOBAL del sitio (aplica a todos los tours y vehículos).
                'default_currency' => 'USD',
                // Pago asistido por WhatsApp: activo por defecto para que un sitio
                // recién instalado, sin pasarela configurada, pueda vender igual.
                'payment_whatsapp_enabled' => true,
                'payment_whatsapp_number' => '',
                // Enlaces de pago del banco: apagado hasta que haya un enlace
                // real que emitir. Ver PAGO-ENLACE-BAC.md.
                'payment_bac_link_enabled' => false,
                'payment_bac_link_hosts' => 'baccredomatic.com, credomatic.com',
                'payment_bac_link_ttl_hours' => 24,
                'payment_bac_dual_control' => false,
                'payment_bac_instructions' => '',
                // Apagado por defecto: cancelar una reserva sin que nadie lo
                // revise es una decisión que debe tomar el operador, no un
                // efecto colateral de instalar la fase. Ver SiteSettings.
                'payment_bac_link_auto_release' => false,
                'currency' => 'USD',
                'currency_symbol' => '$',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'payment_methods' => [
                    'paypal' => [
                        'enabled' => true,
                        'mode' => 'sandbox',
                        'client_id' => '',
                        'client_secret' => '',
                    ],
                    'stripe' => [
                        'enabled' => true,
                        'mode' => 'sandbox',
                        'key' => '',
                        'secret' => '',
                    ],
                    'wompi' => [
                        'enabled' => true,
                        'mode' => 'sandbox',
                        'key' => '',
                        'secret' => '',
                    ],
                ],
            ])],

            ['key' => 'taxes', 'value' => json_encode([
                'iva' => 13,
                'other' => 0,
            ])],

            ['key' => 'shipping', 'value' => json_encode([
                'delivery_time' => '1-3 days',
                'cost' => 5,
            ])],

            ['key' => 'social_auth_services', 'value' => json_encode([
                'facebook' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'google' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'twitter' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'linkedin' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                ],
            ])],

            ['key' => 'mail', 'value' => json_encode([
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ])],
        ];

        foreach ($settings as $setting) {
            Setting::create([
                'id' => Str::uuid(),
                'key' => $setting['key'],
                'value' => $setting['value'],
            ]);
        }
    }
}
