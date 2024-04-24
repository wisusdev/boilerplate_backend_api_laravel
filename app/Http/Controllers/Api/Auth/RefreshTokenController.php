<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();
        $token = $request->user()->createToken('Refresh Token');

        $response = [
            'token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->token->expires_at->toDateTimeString(),
        ];

        return response()->json($response);
    }
}
