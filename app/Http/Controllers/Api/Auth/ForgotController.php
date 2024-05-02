<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotController extends Controller
{
	public function forgot(ForgotRequest $request): JsonResponse
    {
		$email = $request->input('data.attributes.email');
        $user = User::whereEmail($email)->first();
		$token = Str::random(10);

		DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
			'token' => $token,
			'created_at' => now()->addHours(6)
		]);

		$url = config('app.frontend_url').'/auth/reset-password?token='.$token;

		// Send email
		Mail::send('mail.password_reset', ['url' => $url, 'name' => $user->first_name], function ($message) use ($email) {
			$message->to($email);
			$message->subject('Reset your password');
		});

		return response()->json(['message' => 'message.resetPasswordEmailSent']);

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
		if (!$passwordReset->created_at >= now()) {
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

        return response()->json(['message' => 'message.passwordResetSuccess']);
	}
}
