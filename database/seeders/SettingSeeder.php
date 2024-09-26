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
				"name" => config('app.name'),
				"url_api" => config('app.url'),
				"url_frontend" => config('app.frontend_url'),
				"description" => "Laravel api, Angular frontend and Flutter mobile.",
				"logo" => "",
				"favicon" => "",
				"email" => "info@wisus.dev",
				"phone" => "+503 1234-5678",
				"address" => "1234 Main St, San Salvador, El Salvador",
				"timezone" => "America/El_Salvador",
			])],

			['key' => 'payment_gateway', 'value' => json_encode([
				"currency" => "USD",
				"currency_symbol" => "$",
				"decimal_separator" => ".",
				"thousands_separator" => ",",
				"payment_methods" => [
					"paypal" => [
						"enabled" => true,
						"mode" => "sandbox",
						"client_id" => "",
						"client_secret" => ""
					],
					"stripe" => [
						"enabled" => true,
						"mode" => "sandbox",
						"key" => "",
						"secret" => ""
					],
					"wompi" => [
						"enabled" => true,
						"mode" => "sandbox",
						"key" => "",
						"secret" => ""
					]
				],
			])],

			['key' => 'taxes', 'value' => json_encode([
				"iva" => 13,
				"other" => 0
			])],

			['key' => 'shipping', 'value' => json_encode([
				"delivery_time" => "1-3 days",
				"cost" => 5
			])],

			['key' => 'social_auth_services', 'value' => json_encode([
				"facebook" => [
					"enabled" => false,
					"client_id" => "",
					"client_secret" => ""
				],
				"google" => [
					"enabled" => false,
					"client_id" => "",
					"client_secret" => ""
				],
				"twitter" => [
					"enabled" => false,
					"client_id" => "",
					"client_secret" => ""
				],
				"linkedin" => [
					"enabled" => false,
					"client_id" => "",
					"client_secret" => ""
				]
			])],

			['key' => 'mail', 'value' => json_encode([
				"driver" => config('mail.default'),
				"host" => config('mail.mailers.smtp.host'),
				"port" => config('mail.mailers.smtp.port'),
				"encryption" => config('mail.mailers.smtp.encryption'),
				"username" => config('mail.mailers.smtp.username'),
				"password" => config('mail.mailers.smtp.password'),
				"from_address" => config('mail.from.address'),
				"from_name" => config('mail.from.name'),
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
