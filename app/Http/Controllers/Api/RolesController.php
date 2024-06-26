<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RolRequest;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:roles:index')->only('index');
        $this->middleware('can:roles:store')->only('store');
        $this->middleware('can:roles:show')->only('show');
        $this->middleware('can:roles:update')->only('update');
        $this->middleware('can:roles:delete')->only('destroy');
    }

    public function index(): JsonResource
    {
        $roles = Role::query()
            ->sparseFieldset()
            ->jsonPaginate();

        return RoleResource::collection($roles);
    }

    public function store(RolRequest $request): RoleResource
    {
        $role = Role::create([
            'name' => $request->input('data.attributes.name')
        ]);
        $role->givePermissionTo($request->input('data.attributes.permissions'));

        return RoleResource::make($role);
    }

    public function show(Role $role): JsonResource
    {
        return RoleResource::make($role);
    }


    public function update(RolRequest $request, Role $role): RoleResource
    {
        $role->update([
            'name' => $request->input('data.attributes.name')
        ]);
        $role->syncPermissions($request->input('data.attributes.permissions'));

        return RoleResource::make($role);
    }

    public function destroy(Role $role): Response
    {
        $role->delete();
        return response()->noContent();
    }
}
