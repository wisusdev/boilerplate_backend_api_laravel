<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create($data['data']['attributes']);
        $user->assignRole('user');
        $user->sendEmailVerificationNotification();

        return response()->json([
           'data' => [
               'type' => 'users',
               'attributes' => [
                   'status' => true,
                   'message' => 'message.recordCreated',
               ],
           ]
        ], 201);
    }
}
