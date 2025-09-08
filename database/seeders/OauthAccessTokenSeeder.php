<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OauthAccessTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\OauthAccessToken::factory()->count(10)->create();
    }
}
