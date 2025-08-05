<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Requests\RolRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
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

        $role = Role::create([
            'name' => $request->input('data.attributes.name')
        ]);
        $role->givePermissionTo($request->input('data.attributes.permissions'));

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
        $role->update([
            'name' => $request->input('data.attributes.name')
        ]);
        $role->syncPermissions($request->input('data.attributes.permissions'));

        return RoleResource::make($role);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Role $role): JsonResponse | Response
    {
        $this->authorize('delete', $role);

        if ($role->name === 'super-admin' || $role->name === 'admin') {
            return response()->json(['message' => 'Cannot delete the ' . $role->name . ' role'], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a role that has users assigned'], 403);
        }

        $role->delete();
        return response()->noContent();
    }
}
