<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function refreshToken(Request $request): JsonResponse
    {
        // Revocar el token actual
        $request->user()->token()->revoke();

        // Crear un nuevo token con la misma caducidad que en el login: sin fijarla
        // expires_at quedaba nulo y la respuesta reventaba al formatearla.
        $tokenResult = $request->user()->createToken('API Token');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        return response()->json([
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString(),
        ]);
    }
}
