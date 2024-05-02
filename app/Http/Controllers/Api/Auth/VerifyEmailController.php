<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->only('resend');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::where('id', $request->route('id'))->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['validation.invalidEmail']
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'message.emailAlreadyVerified']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'message.emailVerified']);
    }

    /**
     * @throws ValidationException
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['validation.emailAlreadyVerified']
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'message.emailVerificationSent']);
    }
}
