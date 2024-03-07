<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Requests\RegisterRequest;
use Exception;
use App\Models\User;
use App\Http\Controllers\Controller;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        User::create($request->except('password_confirmation'));
        return response()->json(['status' => true], 200);
    }
}
