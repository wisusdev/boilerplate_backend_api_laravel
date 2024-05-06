<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionsController extends Controller
{
    public function index(): JsonResponse
    {
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
