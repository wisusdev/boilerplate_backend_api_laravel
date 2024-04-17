<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Models\DeviceInfo;
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
        $user = User::whereEmail($request->input('data.email'))->first();

        if (!$user || !Hash::check($request->input('data.password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')]
            ]);
        }

        $tokenResult = $user->createToken('Login');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        $country = file_get_contents('http://ip-api.com/json/' . $request->ip() . '?fields=country');

        DeviceInfo::create([
            'login_at' => now(),
            'browser' => $request->header('User-Agent'),
            'os' => $request->server('HTTP_USER_AGENT'),
            'ip' => $request->ip(),
            'country' => json_decode($country)->country ?? 'Unknown',
            'user_id' => $user->id,
            'session_token' => $token->id,
        ]);

        $dataResponse = (object)[
            'user' => $user,
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString(),
        ];

        return LoginResource::make($dataResponse);
    }
}
