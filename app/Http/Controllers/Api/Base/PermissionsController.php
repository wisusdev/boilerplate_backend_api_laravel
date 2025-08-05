<?php

namespace App\Http\Controllers\Api\Base;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;

class PermissionsController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
    {
        $this->authorize('index', Permission::class);

        $permissions = Permission::select('name')->get();
        return response()->json([
                'data' => [
                    'type' => 'permissions',
                    'attributes' => $permissions,
                ]
            ]
        );
    }
}
