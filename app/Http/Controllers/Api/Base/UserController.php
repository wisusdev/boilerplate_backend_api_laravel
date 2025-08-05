<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(): JsonResource
    {
        $this->authorize('index', User::class);

        $users = User::query()
			->allowedFilters(['first_name', 'last_name', 'email', 'username'])
			->allowedSorts(['id', 'first_name', 'last_name', 'email', 'username'])
            ->sparseFieldset()
            ->jsonPaginate();

        return UserResource::collection($users);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(UserRequest $request): UserResource
    {
        $this->authorize('store', User::class);

        $data = $request->validated();
        $user = User::create($data['data']['attributes']);

        $user->assignRole($data['data']['attributes']['roles']);
        $user->sendEmailVerificationNotification();

        return UserResource::make($user);
    }

    /**
     * @throws AuthorizationException
     */
    public function show(User $user): UserResource
    {
        $this->authorize('show', $user);
        return UserResource::make($user);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(UserRequest $request, User $user): JsonResource
    {
        $this->authorize('update', $user);

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

    /**
     * @throws AuthorizationException
     */
    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        // Revoca todos los tokens de acceso del usuario
        $user->tokens()->delete();

        // Elimina también los refresh tokens si los estás usando
        DB::table('oauth_refresh_tokens')
        ->whereIn('access_token_id', $user->tokens()->pluck('id'))
        ->delete();

        // Elimina el usuario
        $user->delete();

        // Elimina el avatar del usuario si existe
        if (isset($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        return response()->noContent();
    }
}
