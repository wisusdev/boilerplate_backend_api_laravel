<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::paginate();
        return response()->json(['data' => $roles]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $role = Role::create($request->except('permissions'));
        $permissions = $request->input('permissions', []);
        $role->syncPermissions($permissions);

        return response()->json(['message' => 'Role created successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::findById($id);
        return response()->json(['data' => $role]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::findById($id);
        $role->update($request->except('permissions'));

        $permissions = $request->input('permissions', []);
        $role->syncPermissions($permissions);

        return response()->json(['message' => 'Role updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findById($id);
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
