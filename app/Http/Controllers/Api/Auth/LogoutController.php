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

        return response()->json(['message' => 'message.loggedOut']);
    }

    public function logoutDevice(Request $request, $id): JsonResponse
    {
        $request->user()->revokeAccessToken($id);
        return response()->json(['message' => 'message.loggedOut']);
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        $request->user()->tokens->each(function ($token) {
            $token->delete();
        });

        return response()->json(['message' => 'message.loggedOutAllDevices']);
    }


}
