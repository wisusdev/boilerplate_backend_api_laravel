<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    /**
     * La ruta va protegida por 'signed:relative'; aquí se comprueba además que el
     * hash corresponda al correo actual del usuario. Sin ambas cosas, bastaba con
     * pedir /auth/email/verify/{id}/loquesea para verificar cualquier cuenta.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::where('id', $request->route('id'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['validation.invalidEmail'],
            ]);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            throw ValidationException::withMessages([
                'email' => ['validation.invalidEmail'],
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
                'email' => ['validation.emailAlreadyVerified'],
            ]);
        }

        $request->user()->notify(new VerifyEmail);

        return response()->json(['message' => 'message.emailVerificationSent']);
    }
}
