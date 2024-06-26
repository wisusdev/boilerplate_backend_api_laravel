<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(10)->create();

        $user = User::create([
            'username' => 'user00',
            'first_name' => 'Jesus',
            'last_name' => 'Avelar',
            'email' => 'user00@wisus.dev',
            'password' => bcrypt('12345678aA'),
        ]);

        $user->assignRole('admin');
    }
}
