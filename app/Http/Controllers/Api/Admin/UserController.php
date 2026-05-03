<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * List users
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::with('role')
            ->when($request->has('role') && $request->role !== '', function ($query) use ($request) {
                $role = $request->role;
                if ($role === 'staff') {
                    $query->whereHas('role', function ($q) {
                        $q->whereIn('name', ['admin', 'employee', 'data_entry', 'accountant']);
                    });
                } else {
                    $query->whereHas('role', function ($q) use ($role) {
                        $q->where('name', $role);
                    });
                }
            })
            ->when(! $request->has('role') || $request->role === '', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', '!=', 'merchant');
                });
            })
            ->when($request->has('search') && $request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->has('status') && $request->status, function ($query) use ($request) {
                if ($request->status === 'blocked') {
                    $query->where('is_blocked', true);
                } elseif ($request->status === 'active') {
                    $query->where(function ($q) {
                        $q->where('is_blocked', false)
                            ->orWhereNull('is_blocked');
                    });
                }
            })
            ->when($request->has('city') && $request->city, function ($query) use ($request) {
                $query->where('city', $request->city);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => UserResource::collection($users->getCollection()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Create user (Admin)
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'language' => 'nullable|in:ar,en',
            'city' => 'required|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'role_id' => $request->role_id,
            'language' => $request->language ?? 'ar',
            'city' => $request->city,
            'country' => 'مصر', // Always Egypt for this application
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user->load('role')),
        ], 201);
    }

    /**
     * Get user details
     */
    public function getUser(string $id): JsonResponse
    {
        $user = User::with(['role', 'orders', 'coupons', 'reviews'])
            ->findOrFail($id);

        $usedCouponsCount = $user->coupons()
            ->where(function ($q) {
                $q->whereNotNull('activated_at')
                    ->orWhere('status', 'used');
            })
            ->count();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'language' => $user->language,
                'city' => $user->city,
                'country' => $user->country,
                'is_blocked' => $user->is_blocked ?? false,
                'role_id' => $user->role_id,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'name_ar' => $user->role->name_ar,
                    'name_en' => $user->role->name_en,
                ] : null,
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
                'total_orders' => $user->orders()->count(),
                'total_coupons' => $user->coupons()->count(),
                'used_coupons_count' => $usedCouponsCount,
                'total_reviews' => $user->reviews()->count(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at ? $user->updated_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:150',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'phone' => 'sometimes|string|unique:users,phone,'.$id,
            'language' => 'sometimes|in:ar,en',
            'role_id' => 'sometimes|exists:roles,id',
            'city' => 'sometimes|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
            'country' => 'sometimes|string|max:100',
            'is_blocked' => 'sometimes|boolean',
        ]);

        $user = User::findOrFail($id);
        $updateData = $request->only(['name', 'email', 'phone', 'language', 'role_id', 'city', 'is_blocked']);
        $updateData['country'] = 'مصر';
        $user->update($updateData);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($user->load('role')),
        ]);
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_'.$user->id.'@deleted.com',
            'phone' => null,
        ]);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Block/Unblock user
     */
    public function blockUser(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'is_blocked' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'is_blocked' => $request->is_blocked,
        ]);

        $message = $request->is_blocked
            ? 'User blocked successfully'
            : 'User unblocked successfully';

        return response()->json([
            'message' => $message,
            'data' => new UserResource($user->load('role')),
        ]);
    }
}
