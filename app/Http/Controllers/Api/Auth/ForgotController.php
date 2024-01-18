<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ForgotController extends Controller
{
    public function forgot(Request $request){
		try {
			$validator = Validator::make($request->all(), [
				'email' => 'required|email|exists:users,email'
			]);

			if ($validator->fails()) {
				return response()->json([
					'errors' => [
						'status' => '422',
						'title'  => 'Validation Error',
						'detail' => $validator->errors(),
					]
				], 422);
			}

			$email = $request->email;

			$data = [
				'errors' => [
					'status' => '400',
					'title'  => 'Bad Request',
					'detail' => 'Invalid credentials',
				]
			];

			if(User::where('email', $email)->doesntExist()){
				return response()->json($data, 400);
			} else {
				$token = Str::random(10);

				DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
					'token' => $token,
					'created_at' => now()->addHours(6)
				]);

				// Send email
				Mail::send('mail.password_reset', ['token' => $token], function($message) use($email){
					$message->to($email);
					$message->subject('Reset your password');
				});

				return response()->json([
					'data' => [
						'attributes' => [
							'status' => 'Success',
							'message' => 'Reset password link sent to your email',
						]
					]
				], 200);
			}

		} catch (\Exception $error) {
			return response()->json([
				'errors' => [
					'status' => '422',
					'title'  => 'Unprocessable Entity',
					'detail' => $error->getMessage(),
				]
			], 422);
		}
	}

	public function reset(Request $request)
	{
		$this->validate($request, [
			'token' => 'required|string',
			'password' => 'required|string|confirmed'
		]);

		$token = $request->token;
		$passwordReset = DB::table('password_reset_tokens')->where('token', $token)->first();

		// verify
		if(!$passwordReset){
			return response()->json(['message' => 'Invalid token'], 404);
		}

		// Validate expire token
		if(!$passwordReset->created_at >= now()){
			return response()->json(['message' => 'Token expired'], 404);
		}

		$user = User::where('email', $passwordReset->email)->first();

		if(!$user){
			return response()->json(['message' => 'User doesn\'t exist', 'user_found' => false], 404);
		}

		$user->password = bcrypt($request->password);
		$user->save();

		DB::table('password_reset_tokens')->where('email', $user->email)->delete();

		return response()->json(['message' => 'Password reset successfully', 'user_found' => true], 200);
	}
}
