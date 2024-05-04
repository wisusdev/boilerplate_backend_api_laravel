<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeviceInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokenId = $user->token()->id;
        $user->token()->revoke();

        $deviceInfo = DeviceInfo::where('session_token', $tokenId)->first();
        if ($deviceInfo) {
            $deviceInfo->delete();
        }

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
