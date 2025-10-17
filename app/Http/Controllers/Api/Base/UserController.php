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
use Throwable;

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
        $dataAttributes = $data['data']['attributes'];

        $user = DB::transaction(function () use ($dataAttributes) {
            $user = User::create($dataAttributes);
            $user->assignRole($dataAttributes['roles']);
            $user->sendEmailVerificationNotification();
            return $user;
        });

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
        
        $data = $request->validated();
        $dataAttributes = $data['data']['attributes'];

        DB::transaction(function () use ($user, $dataAttributes) {
            $originalEmail = $user->email;

            $updates = [
                'username'   => $dataAttributes['username'] ?? null,
                'first_name' => $dataAttributes['first_name'] ?? null,
                'last_name'  => $dataAttributes['last_name'] ?? null,
                'email'      => $dataAttributes['email'] ?? null,
            ];

            if (!empty($dataAttributes['password'])) {
                $updates['password'] = \Illuminate\Support\Facades\Hash::make($dataAttributes['password']);
            }

            // Remove keys with null values (preserve empty strings if intentionally provided)
            $updates = array_filter($updates, function ($v) {
                return $v !== null;
            });

            $user->fill($updates);
            $user->save();

            if (isset($dataAttributes['roles'])) {
                $user->syncRoles($dataAttributes['roles']);
            }

            if (array_key_exists('email', $updates) && $originalEmail !== $updates['email']) {
                $user->email_verified_at = null;
                $user->save(['timestamps' => false]);
                $user->sendEmailVerificationNotification();
            }
        });

        $user->refresh();

        return UserResource::make($user);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            // Revoca todos los tokens de acceso del usuario
            $user->tokens()->delete();

            // Elimina también los refresh tokens si los estás usando
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $user->tokens()->pluck('id'))->delete();

            // Elimina el avatar del usuario si existe
            if (isset($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->delete();
        });

        return response()->noContent();
    }
}
