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
        $data = $request->validated();
        User::create($data['data']);
        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
        ], 201);
    }
}
