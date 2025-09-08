<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OauthRefreshTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\OauthRefreshToken::factory()->count(10)->create();
    }
}
