<?php

namespace App\Http\Controllers\Api\Auth;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
	public function login(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'email' => 'required|email',
				'password' => 'required|string|min:8',
			]);

			if ($validator->fails()) {
				return response()->json($validator->errors(), 422);
			}

			$data = [
				'status' => false,
				'code' => 401,
				'message' => 'Invalid credentials',
			];

			if (auth()->attempt(['email' => $request->email, 'password' => $request->password])) {
				$tokenResult = auth()->user()->createToken('authToken');
				$token = $tokenResult->token;
				$token->expires_at = Carbon::now()->addWeeks(1);
				$token->save();

				$data['type'] = 'Bearer';
				$data['token'] = $tokenResult->accessToken;
				$data['expires_at'] = $tokenResult->token->expires_at;
				$data['status'] = true;
				$data['code'] = 200;
				$data['message'] = 'Login success';

				return response()->json($data, $data['code']);
			}

			return response()->json($data, $data['code']);
			
		} catch (Exception $error) {
			return response()->json(['message' => $error->getMessage(), "statusCode" => 422]);
		}
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

	public function user(Request $request): JsonResponse
	{
		try {
			return response()->json(['status' => true, 'data' => $request->user()], 200);
		} catch (Exception $error) {
			return response()->json(['message' => $error->getMessage(), "statusCode" => 422]);
		}
	}
}
