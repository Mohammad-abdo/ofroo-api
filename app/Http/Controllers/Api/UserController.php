<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Helpers\StorageHelper;

class UserController extends Controller
{
    /**
     * Get authenticated user profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('role');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? null,
                'avatar_url' => $user->avatar ?? null,
                'language' => $user->language ?? 'ar',
                'city' => $user->city,
                'country' => $user->country ?? 'مصر',
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                ] : null,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('users')->ignore($user->id),
            ],
            'language' => 'sometimes|in:ar,en',
            'city' => 'sometimes|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = $validator->validated();

        // Keep country as Egypt
        $updateData['country'] = 'مصر';

        $user->update($updateData);
        $user->load('role');

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar ?? null,
                'avatar_url' => $user->avatar ?? null,
                'language' => $user->language ?? 'ar',
                'city' => $user->city,
                'country' => $user->country,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                ] : null,
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Update user phone number
     */
    public function updatePhone(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update([
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Phone number updated successfully',
            'data' => [
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('avatar');
        
        // Validate image
        $validation = StorageHelper::validateImage($file, 2);
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Invalid image file',
                'error' => $validation['error'],
            ], 422);
        }

        // Delete old avatar if exists
        if ($user->avatar) {
            StorageHelper::deleteFile($user->avatar);
        }

        // Upload new avatar using StorageHelper
        $uploadResult = StorageHelper::uploadUserAvatar($file, $user->id);
        $avatarUrl = $uploadResult['url'];

        // Update user avatar URL (not path)
        $user->update(['avatar' => $avatarUrl]);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar' => $avatarUrl,
                'avatar_url' => $avatarUrl,
            ],
        ]);
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            StorageHelper::deleteFile($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json([
            'message' => 'Avatar deleted successfully',
        ]);
    }

    /**
     * Get user notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        $notifications = $user->notifications()
            ->when($request->has('is_read'), function ($query) use ($request) {
                if ($request->boolean('is_read')) {
                    $query->whereNotNull('read_at');
                } else {
                    $query->whereNull('read_at');
                }
            })
            ->when($request->has('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where('data', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $notifications->getCollection()->map(function ($notification) {
                $data = json_decode($notification->data, true);
                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'info',
                    'title_ar' => $data['title_ar'] ?? $data['title'] ?? '',
                    'title_en' => $data['title_en'] ?? $data['title'] ?? '',
                    'message_ar' => $data['message_ar'] ?? $data['message'] ?? '',
                    'message_en' => $data['message_en'] ?? $data['message'] ?? '',
                    'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete notification
     */
    public function deleteNotification(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Get user statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get orders count
        $ordersCount = \App\Models\Order::where('user_id', $user->id)->count();
        
        // Get active coupons count (wallet coupons)
        $activeCouponsCount = \App\Models\Coupon::where('user_id', $user->id)
            ->whereIn('status', ['active', 'activated'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        // Get total spent
        $totalSpent = \App\Models\Order::where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Get loyalty points (if exists)
        $loyaltyPoints = 0;
        $loyaltyAccount = \App\Models\LoyaltyPoint::where('user_id', $user->id)->first();
        if ($loyaltyAccount) {
            $loyaltyPoints = $loyaltyAccount->total_points ?? 0;
        }

        return response()->json([
            'data' => [
                'orders_count' => $ordersCount,
                'active_coupons_count' => $activeCouponsCount,
                'total_spent' => (float) $totalSpent,
                'loyalty_points' => $loyaltyPoints,
            ],
        ]);
    }

    /**
     * Get user settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'language' => $user->language ?? 'ar',
                'notifications_enabled' => $user->notifications_enabled ?? true,
                'email_notifications' => $user->email_notifications ?? true,
                'push_notifications' => $user->push_notifications ?? true,
            ],
        ]);
    }

    /**
     * Update user settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'language' => 'sometimes|in:ar,en',
            'notifications_enabled' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update($validator->validated());

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => [
                'language' => $user->language ?? 'ar',
                'notifications_enabled' => $user->notifications_enabled ?? true,
                'email_notifications' => $user->email_notifications ?? true,
                'push_notifications' => $user->push_notifications ?? true,
            ],
        ]);
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect',
            ], 422);
        }

        // Anonymize user data (GDPR compliance)
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@deleted.com',
            'phone' => null,
        ]);

        // Delete avatar if exists
        if ($user->avatar && Storage::exists($user->avatar)) {
            Storage::delete($user->avatar);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user account
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }

    /**
     * Get user orders history
     */
    public function getOrdersHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        $orders = \App\Models\Order::with(['items.offer.category', 'merchant'])
            ->where('user_id', $user->id)
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $orders->getCollection()->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => (float) $order->total_amount,
                    'items_count' => $order->items->count(),
                    'merchant' => $order->merchant ? [
                        'id' => $order->merchant->id,
                        'company_name' => $order->merchant->company_name,
                        'logo_url' => $order->merchant->logo_url,
                    ] : null,
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}

