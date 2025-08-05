<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->token()->revoke();

        return response()->json([
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.loggedOut',
                ],
            ]
        ]);
    }
}
