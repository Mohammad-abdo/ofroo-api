<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * List all permissions
     */
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group');

        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Create permission
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'group' => 'required|string',
            'name_ar' => 'nullable|string',
            'name_en' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create($request->all());

        return response()->json([
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    /**
     * Update permission
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->update($request->all());

        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ]);
    }

    /**
     * Delete permission
     */
    public function delete(string $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * List all roles
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Create role
     */
    public function createRole(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'name_ar' => 'nullable|string',
            'name_en' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $role = Role::create($request->all());

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    /**
     * Update role
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->update($request->all());

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $role,
        ]);
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($id);
        $role->permissions()->sync($request->permissions);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * Delete role
     */
    public function deleteRole(string $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}
