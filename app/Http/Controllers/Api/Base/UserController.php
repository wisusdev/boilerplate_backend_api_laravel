<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

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
        $roles = (array) ($data['data']['attributes']['roles'] ?? []);
        $this->assertCanAssignRoles($roles);

        $user = User::create($data['data']['attributes']);

        $user->assignRole($roles);
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

        $roles = (array) $request->input('data.attributes.roles', []);
        $this->assertCanAssignRoles($roles, $user);

        // Se captura ANTES del update: comparar después dejaba la condición
        // siempre en falso y el correo se cambiaba conservando la verificación.
        $originalEmail = $user->email;

        $data = [
            'username' => $request->input('data.attributes.username'),
            'first_name' => $request->input('data.attributes.first_name'),
            'last_name' => $request->input('data.attributes.last_name'),
            'email' => $request->input('data.attributes.email'),
        ];

        if ($request->has('data.attributes.password') && ! empty($request->input('data.attributes.password'))) {
            $data['password'] = $request->input('data.attributes.password');
        }

        $user->update($data);

        if ($originalEmail !== $user->email) {
            $user->email_verified_at = null;
            $user->save(['timestamps' => false]);
            $user->sendEmailVerificationNotification();
            // Un cambio de correo invalida las sesiones abiertas.
            $user->tokens()->delete();
        }

        $user->syncRoles($roles);

        return UserResource::make($user);
    }

    /**
     * Nadie puede otorgar un rol cuyos permisos no posee, ni tocar sus propios
     * roles. Sin esto, cualquiera con 'users:update' se ascendía a admin.
     *
     * @param  array<int, string>  $roles
     */
    private function assertCanAssignRoles(array $roles, ?User $target = null): void
    {
        $actor = auth()->user();

        if ($actor->hasRole('superadmin')) {
            return;
        }

        if ($target && $target->getKey() === $actor->getKey()
            && $target->getRoleNames()->sort()->values()->all() !== collect($roles)->sort()->values()->all()) {
            abort(403, 'No puedes modificar tus propios roles.');
        }

        if (in_array('superadmin', $roles, true)) {
            abort(403, 'Solo un superadmin puede otorgar el rol superadmin.');
        }

        $held = $actor->getAllPermissions()->pluck('name');

        foreach (Role::whereIn('name', $roles)->with('permissions')->get() as $role) {
            $excess = $role->permissions->pluck('name')->diff($held);

            if ($excess->isNotEmpty()) {
                abort(403, "No puedes otorgar el rol '{$role->name}': incluye permisos que no posees.");
            }
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        // Soft delete: UserObserver revoca las sesiones y conserva el avatar
        // (borrarlo aquí dejaba la fila apuntando a un fichero inexistente).
        // El historial de reservas se mantiene: bookings.user_id es RESTRICT.
        $user->delete();

        return response()->noContent();
    }
}
