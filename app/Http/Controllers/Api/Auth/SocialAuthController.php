<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        $validated = $this->validateProvider($provider);
        if (!is_null($validated)) {
            return $validated;
        }

        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        $validated = $this->validateProvider($provider);

        if (!is_null($validated)) {
            return $validated;
        }

        $user = Socialite::driver($provider)->user();

        $userCreated = User::firstOrCreate(
            [
                'email' => $user->getEmail()
            ],
            [
                'email_verified_at' => now(),
                'name' => $user->getName(),
                'status' => true,
            ]
        );

        event(new Registered($userCreated));

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
        ], 201);
    }

    protected function validateProvider($provider)
    {
        if (!in_array($provider, ['facebook', 'twitter', 'github', 'google'])) {
            return response()->json(['error' => 'Please login using facebook, github or google'], 422);
        }
    }
}
