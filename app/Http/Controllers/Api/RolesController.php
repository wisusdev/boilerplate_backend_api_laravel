<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class RolesController extends Controller
{

    public function index(): JsonResource
    {
        $roles = Role::query()
            ->sparseFieldset()
            ->jsonPaginate();

        return RoleResource::collection($roles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'data.attributes.role.name' => ['required', 'string', 'unique:roles,name', 'max:255', 'min:3'],
            'data.attributes.permissions' => ['array'],
        ]);

        $role = Role::create($request->input('data.attributes.role'));
        $role->givePermissionTo($request->input('data.attributes.permissions'));

        return RoleResource::make($role);
    }

    public function show(Role $role): JsonResource
    {
        return RoleResource::make($role);
    }


    public function update(Request $request, Role $role): RoleResource
    {
        $request->validate([
            'data.attributes.role.name' => ['required', 'string', 'max:255', 'min:3', 'unique:roles,name,' . $role->uuid . ',uuid'],
            'data.attributes.permissions' => ['array'],
        ]);

        $role->update($request->input('data.attributes.role'));
        $role->syncPermissions($request->input('data.attributes.permissions'));

        return RoleResource::make($role);
    }

    public function destroy(Role $role): Response
    {
        $role->delete();
        return response()->noContent();
    }
}
