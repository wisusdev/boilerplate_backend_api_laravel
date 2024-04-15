<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Models\User;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{

    public function login(LoginRequest $request): JsonResource
    {
        $user = User::whereEmail($request['data']['email'])->first();

        if (!$user || !Hash::check($request['data']['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')]
            ]);
        }

        $deviceInfo = $request->header('User-Agent', 'Unknown');

        $tokenResult = $user->createToken('authToken on ' . $deviceInfo);
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        $dataResponse = (object)[
            'user' => $user,
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString(),
        ];

        return LoginResource::make($dataResponse);
    }
}
