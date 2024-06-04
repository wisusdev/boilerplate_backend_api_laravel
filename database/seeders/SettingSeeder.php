<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            ['key' => 'app_name', 'value' => config('app.name')],
            ['key' => 'app_url', 'value' => config('app.url')],

            ['key' => 'currency', 'value' => 'USD'],
            ['key' => 'currency_symbol', 'value' => '$'],

            ['key' => 'tax', 'value' => '0'],
            ['key' => 'shipping_cost', 'value' => '0'],

            ['key' => 'paypal_mode', 'value' => 'sandbox'],
            ['key' => 'paypal_client_id', 'value' => ''],
            ['key' => 'paypal_secret', 'value' => ''],
            ['key' => 'paypal_status', 'value' => 'active'],

            ['key' => 'stripe_mode', 'value' => 'sandbox'],
            ['key' => 'stripe_key', 'value' => ''],
            ['key' => 'stripe_secret', 'value' => ''],
            ['key' => 'stripe_status', 'value' => 'inactive'],

            ['key' => 'wompi_mode', 'value' => 'sandbox'],
            ['key' => 'wompi_key', 'value' => ''],
            ['key' => 'wompi_secret', 'value' => ''],
            ['key' => 'wompi_status', 'value' => 'inactive'],

            ['key' => 'serfinsa_mode', 'value' => 'sandbox'],
            ['key' => 'serfinsa_key', 'value' => ''],
            ['key' => 'serfinsa_secret', 'value' => ''],
            ['key' => 'serfinsa_status', 'value' => 'inactive'],

            ['key' => 'n1co_mode', 'value' => 'sandbox'],
            ['key' => 'n1co_key', 'value' => ''],
            ['key' => 'n1co_secret', 'value' => ''],
            ['key' => 'n1co_status', 'value' => 'inactive'],
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
