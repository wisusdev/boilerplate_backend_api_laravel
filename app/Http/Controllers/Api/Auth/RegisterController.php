<?php

namespace App\Http\Controllers\Api\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|unique:users|max:255',
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
                'email' => 'required|unique:users|max:255',
                'password' => 'required|confirmed|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $userData = User::create($request->except('password_confirmation'));

            return response()->json(['status' => true], 200);

        } catch (Exception $error) {
            return response()->json(['status' => $error->getMessage(), "statusCode" => 400]);
        }
    }
}
