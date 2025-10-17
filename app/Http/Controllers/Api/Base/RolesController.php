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
use Illuminate\Support\Facades\DB;

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

        $validated = $request->validated();
        $data = $validated['data']['attributes'];

        $role->update([
            'name' => $data['name']
        ]);

        $role->syncPermissions($data['permissions']);

        return RoleResource::make($role);
    }

	/**
	 * @throws AuthorizationException
	 * @throws \Throwable
	 */
    public function destroy(Role $role): JsonResponse | Response
    {
        $this->authorize('delete', $role);

		if ($role->users()->exists()) {
			return response()->json([
				'errors' => [
					'status' => '422',
					'title' => 'No se puede eliminar',
					'detail' => 'El rol tiene usuarios asignados y no puede ser eliminado.',
				]
			]);
		}

		DB::transaction(function () use ($role) {
			$role->delete();
		});

        return response()->noContent();
    }
}
