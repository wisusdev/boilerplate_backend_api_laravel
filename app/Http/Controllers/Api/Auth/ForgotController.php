<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotController extends Controller
{
	public function forgot(Request $request): JsonResponse
    {
		$request->validate([
			'data.email' => ['required', 'email', 'exists:users,email']
		]);

		$email = $request->data['email'];

		$token = Str::random(10);

		DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
			'token' => $token,
			'created_at' => now()->addHours(6)
		]);

		$url = config('app.frontend_url').'/auth/reset-password?token='.$token;

		// Send email
		Mail::send('mail.password_reset', ['url' => $url], function ($message) use ($email) {
			$message->to($email);
			$message->subject('Reset your password');
		});

		return response()->json(['message' => 'Reset password email sent']);

	}

	public function reset(Request $request): JsonResponse
    {
		$this->validate($request, [
			'data.token' => 'required|string',
			'data.password' => 'required|string|confirmed'
		]);

		$token = $request->data['token'];
		$passwordReset = DB::table('password_reset_tokens')->where('token', $token)->first();

		// verify
		if (!$passwordReset) {
            throw ValidationException::withMessages([
                'token' => ['Invalid token'],
            ]);
		}

		// Validate expire token
		if (!$passwordReset->created_at >= now()) {
            throw ValidationException::withMessages([
                'token' => ['Token expired'],
            ]);
		}

		$user = User::whereEmail($passwordReset->email)->first();

		if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['User doesn\'t exist'],
            ]);
		}

		$user->save();

		DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json(['message' => 'Password reset successfully']);
	}
}
