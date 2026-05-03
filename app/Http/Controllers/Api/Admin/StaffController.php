<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    /**
     * Get all staff (Admin)
     */
    public function getStaff(Request $request): JsonResponse
    {
        $adminRole = Role::where('name', 'admin')->first();

        $query = User::with('role')
            ->where('role_id', $adminRole ? $adminRole->id : null);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $staff = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => UserResource::collection($staff->getCollection()),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ],
        ]);
    }

    /**
     * Get single staff member (Admin)
     */
    public function getStaffMember(string $id): JsonResponse
    {
        $staff = User::with('role')->findOrFail($id);

        if ($staff->role->name !== 'admin') {
            return response()->json([
                'message' => 'User is not an admin staff member',
            ], 422);
        }

        return response()->json([
            'data' => new UserResource($staff),
        ]);
    }

    /**
     * Create staff (Admin)
     */
    public function createStaff(Request $request): JsonResponse
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'language' => 'nullable|in:ar,en',
            'city' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'role_id' => $adminRole->id,
            'language' => $request->language ?? 'ar',
            'city' => $request->city,
            'country' => 'مصر',
        ]);

        return response()->json([
            'message' => 'Staff member created successfully',
            'data' => new UserResource($staff->load('role')),
        ], 201);
    }

    /**
     * Update staff (Admin)
     */
    public function updateStaff(Request $request, string $id): JsonResponse
    {
        $staff = User::with('role')->findOrFail($id);

        if ($staff->role->name !== 'admin') {
            return response()->json([
                'message' => 'User is not an admin staff member',
            ], 422);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'phone' => 'sometimes|string|max:20',
            'password' => 'sometimes|string|min:8|confirmed',
            'language' => 'sometimes|in:ar,en',
            'city' => 'sometimes|string|max:255',
        ]);

        $updateData = $request->only(['name', 'email', 'phone', 'language', 'city']);
        if ($request->has('password')) {
            $updateData['password'] = bcrypt($request->password);
        }

        $staff->update($updateData);

        return response()->json([
            'message' => 'Staff member updated successfully',
            'data' => new UserResource($staff->fresh()->load('role')),
        ]);
    }

    /**
     * Delete staff (Admin)
     */
    public function deleteStaff(string $id): JsonResponse
    {
        $staff = User::with('role')->findOrFail($id);

        if ($staff->role->name !== 'admin') {
            return response()->json([
                'message' => 'User is not an admin staff member',
            ], 422);
        }

        if ($staff->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account',
            ], 422);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff member deleted successfully',
        ]);
    }
}
