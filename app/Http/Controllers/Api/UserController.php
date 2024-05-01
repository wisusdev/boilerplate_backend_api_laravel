<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(): JsonResource
    {
        $users = User::query()
            ->sparseFieldset()
            ->jsonPaginate();

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): UserResource
    {
        $data = $request->validated();
        $user = User::create($data['data']['attributes']);

        $user->assignRole($data['data']['attributes']['roles']);
        $user->sendEmailVerificationNotification();

        return UserResource::make($user);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    public function update(UserRequest $request, User $user): JsonResource
    {
        $data = [
            'username' => $request->input('data.attributes.username'),
            'first_name' => $request->input('data.attributes.first_name'),
            'last_name' => $request->input('data.attributes.last_name'),
            'email' => $request->input('data.attributes.email'),
        ];

        if ($request->has('data.attributes.password') && !empty($request->input('data.attributes.password'))) {
            $data['password'] = $request->input('data.attributes.password');
        }

        $user->update($data);

        if($user->email !== $request->input('data.attributes.email')) {
            $user->email_verified_at = null;
            $user->save(['timestamps' => false]);
            $user->sendEmailVerificationNotification();
        }

        $user->syncRoles($request->input('data.attributes.roles'));

        return UserResource::make($user);
    }

    public function destroy(User $user): Response
    {
        $user->devices()->delete();
        $user->tokens()->revoke();
        $user->delete();
        return response()->noContent();
    }
}
