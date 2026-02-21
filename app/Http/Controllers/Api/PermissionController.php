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
     * List all permissions (flat array for admin UI; optional grouped via ?grouped=1)
     */
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        if ($request->boolean('grouped')) {
            return response()->json([
                'data' => $permissions->groupBy('group'),
            ]);
        }

        return response()->json([
            'data' => $permissions->values()->all(),
        ]);
    }

    /**
     * Create permission
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'group' => 'required|string|max:50',
            'name_ar' => 'nullable|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ]);

        $permission = Permission::create($request->only([
            'name', 'name_ar', 'name_en', 'group', 'description', 'description_ar', 'description_en',
        ]));

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
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ]);

        $payload = $request->only([
            'name', 'name_ar', 'name_en', 'description', 'description_ar', 'description_en',
        ]);
        $payload['guard_name'] = $request->filled('guard_name') ? $request->input('guard_name') : 'web';

        $role = Role::create($payload);

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
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $ids = $request->input('permission_ids', $request->input('permissions', []));
        $role = Role::findOrFail($id);
        $role->permissions()->sync($ids);

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
