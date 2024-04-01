<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->delete();

        Setting::insert([
            ['key' => 'app_name', 'value' => config('app.name')],
            ['key' => 'app_description', 'value' => 'Laravel API Boilerplate'],
            ['key' => 'app_url', 'value' => config('app.url')],
            ['key' => 'app_logo', 'value' => ''],
            ['key' => 'app_favicon', 'value' => ''],
            ['key' => 'app_email', 'value' => 'info@wisus.dev'],
            ['key' => 'app_phone', 'value' => '+1234567890'],
            ['key' => 'app_address', 'value' => '123 Street, City, Country'],
            ['key' => 'app_timezone', 'value' => 'UTC'],
            ['key' => 'app_currency_code', 'value' => 'USD'],
            ['key' => 'app_locale', 'value' => 'en'],
            ['key' => 'app_date_format', 'value' => 'Y-m-d'],
            ['key' => 'app_time_format', 'value' => 'H:i:s'],
            ['key' => 'app_datetime_format', 'value' => 'Y-m-d H:i:s'],
            ['key' => 'app_google_analytics', 'value' => ''],
            ['key' => 'app_google_recaptcha', 'value' => ''],
            ['key' => 'app_mail_driver', 'value' => 'smtp'],
            ['key' => 'app_mail_host', 'value' => 'smtp.mailtrap.io'],
            ['key' => 'app_mail_port', 'value' => '2525'],
            ['key' => 'app_mail_username', 'value' => ''],
            ['key' => 'app_mail_password', 'value' => ''],
            ['key' => 'app_mail_encryption', 'value' => 'tls'],
            ['key' => 'app_mail_from_address', 'value' => ''],
            ['key' => 'auth_facebook_key', 'value' => ''],
            ['key' => 'auth_facebook_secret', 'value' => ''],
            ['key' => 'auth_google_key', 'value' => ''],
            ['key' => 'auth_google_secret', 'value' => ''],
            ['key' => 'auth_github_key', 'value' => ''],
            ['key' => 'auth_github_secret', 'value' => ''],
            ['key' => 'auth_linkedin_key', 'value' => ''],
            ['key' => 'auth_linkedin_secret', 'value' => ''],
            ['key' => 'auth_twitter_key', 'value' => ''],
            ['key' => 'auth_twitter_secret', 'value' => ''],
            ['key' => 'auth_bitbucket_key', 'value' => ''],
            ['key' => 'auth_bitbucket_secret', 'value' => ''],
            ['key' => 'auth_gitlab_key', 'value' => ''],
            ['key' => 'auth_gitlab_secret', 'value' => ''],
            ['key' => 'auth_sms_enable', 'value' => '0'],
            ['key' => 'auth_sms_driver', 'value' => 'twilio'],
            ['key' => 'auth_sms_twilio_sid', 'value' => ''],
            ['key' => 'auth_sms_twilio_token', 'value' => ''],
            ['key' => 'auth_sms_twilio_from', 'value' => ''],
            ['key' => 'auth_sms_nexmo_key', 'value' => ''],
            ['key' => 'auth_sms_nexmo_secret', 'value' => ''],
            ['key' => 'auth_sms_nexmo_from', 'value' => ''],
            ['key' => 'auth_sms_plivo_auth_id', 'value' => ''],
            ['key' => 'auth_sms_plivo_auth_token', 'value' => ''],
            ['key' => 'auth_sms_plivo_from', 'value' => ''],
        ]);
    }
}
