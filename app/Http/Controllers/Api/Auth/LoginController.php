<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResource
    {
        $user = User::whereEmail($request->input('data.attributes.email'))->first();

        if (!$user || !Hash::check($request->input('data.attributes.password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['validation.invalidCredentials']
            ]);
        }

        $limitAuthDevices = intval(config('auth.limit_auth_devices'));

        if($limitAuthDevices > 0) {
            $validTokens = $user->tokens()
                ->where('revoked', 0)
                ->where('expires_at', '>', Carbon::now())
                ->get();

            $tokenCount = $validTokens->count();

            if($tokenCount >= $limitAuthDevices) {
                throw ValidationException::withMessages([
                    'email' => ['validation.limitAuthDevices']
                ]);
            }
        }

        $tokenResult = $user->createToken('Login');
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
