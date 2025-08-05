<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\ForgotPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotController extends Controller
{
	public function forgot(ForgotRequest $request): JsonResponse
    {
		$email = $request->input('data.attributes.email');
        $user = User::whereEmail($email)->first();
		$token = Str::random(60); // Asegúrate de que el token sea lo suficientemente largo

		DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
			'token' => $token,
			'created_at' => now()->addHours(6)
		]);

		$url = config('app.frontend_url').'/auth/reset-password?token='.$token;

		// Send email
		$user->notify(new ForgotPassword($url, $user->first_name));

		return response()->json([
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.resetPasswordEmailSent',
                ],
            ]
        ]);

	}

	public function reset(ResetPasswordRequest $request): JsonResponse
    {
		$token = $request->input('data.attributes.token');
		$passwordReset = DB::table('password_reset_tokens')->where('token', $token)->first();

		// verify
		if (!$passwordReset) {
            throw ValidationException::withMessages([
                'token' => ['validation.tokenInvalid'],
            ]);
		}

		// Validate expire token
		if ($passwordReset->created_at < now()) {
            throw ValidationException::withMessages([
                'token' => ['validation.tokenExpired'],
            ]);
		}

		$user = User::whereEmail($passwordReset->email)->first();

		if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['validation.userNotFound'],
            ]);
		}

		$user->save();

		DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json([
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.passwordResetSuccess',
                ],
            ]
        ]);
	}
}
