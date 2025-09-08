<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OauthAuthCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\OauthAuthCode::factory()->count(10)->create();
    }
}
