<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * @throws ValidationException
     */
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
