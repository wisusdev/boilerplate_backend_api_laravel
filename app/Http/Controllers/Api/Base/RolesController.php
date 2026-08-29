<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class RolesController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(): JsonResource
    {
        $this->authorize('index', Role::class);

        $roles = Role::query()
            ->sparseFieldset()
            ->jsonPaginate();

        return RoleResource::collection($roles);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(RolRequest $request): RoleResource
    {
        $this->authorize('store', Role::class);

        $permissions = (array) $request->input('data.attributes.permissions', []);
        $this->assertCanGrant($permissions);

        $role = Role::create([
            'name' => $request->input('data.attributes.name'),
        ]);
        $role->givePermissionTo($permissions);

        return RoleResource::make($role);
    }

    /**
     * @throws AuthorizationException
     */
    public function show(Role $role): JsonResource
    {
        $this->authorize('show', $role);

        return RoleResource::make($role);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(RolRequest $request, Role $role): RoleResource
    {
        $this->authorize('update', $role);

        // Los roles fundacionales no se reconfiguran por API: 'admin' es grant-all
        // y 'superadmin' pasa cualquier check vía Gate::before.
        if (in_array($role->name, ['admin', 'superadmin'], true) && ! $this->actor()->hasRole('superadmin')) {
            abort(403, 'No se puede modificar el rol '.$role->name.'.');
        }

        $permissions = (array) $request->input('data.attributes.permissions', []);
        $this->assertCanGrant($permissions);

        $role->update([
            'name' => $request->input('data.attributes.name'),
        ]);
        $role->syncPermissions($permissions);

        return RoleResource::make($role);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Role $role): JsonResponse|Response
    {
        $this->authorize('delete', $role);

        if ($role->name === 'superadmin' || $role->name === 'admin') {
            return response()->json(['message' => 'Cannot delete the '.$role->name.' role'], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a role that has users assigned'], 403);
        }

        $role->delete();

        return response()->noContent();
    }

    private function actor(): User
    {
        return auth()->user();
    }

    /**
     * Nadie puede conceder un permiso que él mismo no tiene. Sin esta regla, un
     * usuario con 'roles:update' se otorgaba permisos de administración
     * reconfigurando su propio rol.
     *
     * @param  array<int, string>  $permissions
     */
    private function assertCanGrant(array $permissions): void
    {
        $actor = $this->actor();

        if ($actor->hasRole('superadmin')) {
            return;
        }

        $held = $actor->getAllPermissions()->pluck('name')->all();
        $excess = array_values(array_diff($permissions, $held));

        if ($excess !== []) {
            abort(403, 'No puedes otorgar permisos que no posees: '.implode(', ', $excess));
        }
    }
}
