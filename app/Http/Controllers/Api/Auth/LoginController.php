<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
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

        if (! $user || ! Hash::check($request->input('data.attributes.password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['validation.invalidCredentials'],
            ]);
        }

        $limitAuthDevices = intval(config('auth.limit_auth_devices'));
        $lock = null;

        if ($limitAuthDevices > 0) {
            // Sin este candado, dos logins concurrentes del mismo usuario podían
            // leer el mismo conteo de tokens válidos antes de que ninguno creara
            // el suyo, y ambos pasaban el límite (condición de carrera). El
            // candado se mantiene desde el conteo hasta crear el token, no solo
            // durante el conteo.
            $lock = Cache::lock("login-device-limit:{$user->id}", 10);
            $lock->block(5);

            $tokenCount = $user->tokens()
                ->where('revoked', 0)
                ->where('expires_at', '>', Carbon::now())
                ->count();

            if ($tokenCount >= $limitAuthDevices) {
                $lock->release();

                throw ValidationException::withMessages([
                    'email' => ['validation.limitAuthDevices'],
                ]);
            }
        }

        $tokenResult = $user->createToken('Login');

        optional($lock)->release();

        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        $dataResponse = (object) [
            'user' => $user,
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString(),
        ];

        return LoginResource::make($dataResponse);
    }
}
