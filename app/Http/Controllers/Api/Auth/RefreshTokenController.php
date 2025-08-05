<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function refreshToken(Request $request): JsonResponse
    {
        // Revocar el token actual
        $request->user()->token()->revoke();

        // Crear un nuevo token
        $token = $request->user()->createToken('API Token');

        $response = [
            'token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->token->expires_at->toDateTimeString(),
        ];

        return response()->json($response);
    }
}
