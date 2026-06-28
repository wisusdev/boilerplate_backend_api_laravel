<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $attributes = $request->validated()['data']['attributes'];

        // El username es opcional; si no se envía, se genera uno único.
        if (empty($attributes['username'])) {
            $attributes['username'] = $this->generateUsername(
                $attributes['email'] ?? $attributes['first_name'] ?? 'user'
            );
        }

        $user = User::create($attributes);
        $user->assignRole('user');
        $user->sendEmailVerificationNotification();

        return response()->json([
           'data' => [
               'type' => 'users',
               'attributes' => [
                   'status' => true,
                   'message' => 'message.recordCreated',
               ],
           ]
        ], 201);
    }

    /**
     * Genera un username único a partir de un texto base (email o nombre).
     */
    private function generateUsername(string $seed): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9]/i', '', Str::before($seed, '@')));
        $base = $base !== '' ? $base : 'user';

        $username = $base;
        while (User::query()->where('username', $username)->exists()) {
            $username = $base . random_int(100, 9999);
        }

        return $username;
    }
}
