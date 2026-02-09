<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantStaffController extends Controller
{
    /**
     * List merchant staff
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $staff = MerchantStaff::where('merchant_id', $merchant->id)
            ->with('user')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => $staff,
        ]);
    }

    /**
     * Add staff member
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:manager,staff,cashier,scanner',
            'permissions' => 'nullable|array',
            'can_create_offers' => 'boolean',
            'can_edit_offers' => 'boolean',
            'can_activate_coupons' => 'boolean',
            'can_view_reports' => 'boolean',
            'can_manage_staff' => 'boolean',
        ]);

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        // Check if user is already staff
        if (MerchantStaff::where('merchant_id', $merchant->id)
            ->where('user_id', $request->user_id)
            ->exists()) {
            return response()->json([
                'message' => 'User is already a staff member',
            ], 400);
        }

        $staff = MerchantStaff::create(array_merge($request->all(), [
            'merchant_id' => $merchant->id,
        ]));

        return response()->json([
            'message' => 'Staff member added successfully',
            'data' => $staff->load('user'),
        ], 201);
    }

    /**
     * Update staff member
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $staff = MerchantStaff::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        $staff->update($request->all());

        return response()->json([
            'message' => 'Staff member updated successfully',
            'data' => $staff->fresh()->load('user'),
        ]);
    }

    /**
     * Remove staff member
     */
    public function delete(string $id): JsonResponse
    {
        $user = request()->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $staff = MerchantStaff::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        $staff->update(['is_active' => false]);

        return response()->json([
            'message' => 'Staff member removed successfully',
        ]);
    }
}
