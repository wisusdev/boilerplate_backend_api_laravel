<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        $user->assignRole('user');
        $user->sendEmailVerificationNotification();

        return UserResource::make($user);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    public function update(Request $request, User $user): JsonResource
    {
        $request->validate([
            'data.attributes.username' => ['required', 'string', 'max:255'],
            'data.attributes.first_name' => ['required', 'string', 'max:255'],
            'data.attributes.last_name' => ['required', 'string', 'max:255'],
            'data.attributes.email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id . ',id'],
            'data.attributes.password' => ['nullable', 'string', 'min:8'],
            'data.attributes.roles' => ['array'],
        ]);

        $user->update($request->input('data.attributes'));
        $user->syncRoles($request->input('data.attributes.roles'));

        return UserResource::make($user);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->noContent();
    }
}
