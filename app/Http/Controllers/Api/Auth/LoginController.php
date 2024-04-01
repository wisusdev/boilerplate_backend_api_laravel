<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $requestData = $request->validated();

        $user = User::whereEmail($requestData['data']['email'])->first();

        if (!$user || !Hash::check($requestData['data']['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')]
            ]);
        }

        $tokenResult = $user->createToken('authToken ' . $requestData['data']['device_name']);
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString()
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->token()->revoke();
            return response()->json(['status' => true, 'message' => 'Successfully logged out'], 200);
        } catch (Exception $error) {
            return response()->json(['message' => $error->getMessage(), "statusCode" => 422]);
        }
    }
}
