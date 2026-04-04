<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivationReport;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MerchantStaffController extends Controller
{
    /**
     * Default permission sets per staff role (aligned with MerchantStaffSeeder).
     *
     * @return array{ar: string, en: string, can_create_offers: bool, can_edit_offers: bool, can_activate_coupons: bool, can_view_reports: bool, can_manage_staff: bool}
     */
    protected function permissionsForRole(string $role): array
    {
        $map = [
            'manager' => [
                'ar' => 'مدير', 'en' => 'Manager',
                'can_create_offers' => true, 'can_edit_offers' => true,
                'can_activate_coupons' => true, 'can_view_reports' => true, 'can_manage_staff' => true,
            ],
            'cashier' => [
                'ar' => 'كاشير', 'en' => 'Cashier',
                'can_create_offers' => false, 'can_edit_offers' => false,
                'can_activate_coupons' => true, 'can_view_reports' => false, 'can_manage_staff' => false,
            ],
            'scanner' => [
                'ar' => 'ماسح باركود', 'en' => 'Barcode Scanner',
                'can_create_offers' => false, 'can_edit_offers' => false,
                'can_activate_coupons' => true, 'can_view_reports' => false, 'can_manage_staff' => false,
            ],
            'coupon_activation' => [
                'ar' => 'موظف تفعيل كوبونات', 'en' => 'Coupon Activation Staff',
                'can_create_offers' => false, 'can_edit_offers' => false,
                'can_activate_coupons' => true, 'can_view_reports' => false, 'can_manage_staff' => false,
            ],
            'staff' => [
                'ar' => 'موظف', 'en' => 'Staff',
                'can_create_offers' => false, 'can_edit_offers' => true,
                'can_activate_coupons' => true, 'can_view_reports' => true, 'can_manage_staff' => false,
            ],
        ];

        return $map[$role] ?? $map['staff'];
    }

    /**
     * List merchant staff (all rows; includes inactive for dashboard management).
     */
    public function index(Request $request): JsonResponse
    {
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();

        $rows = MerchantStaff::where('merchant_id', $merchant->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $data = $rows->map(function (MerchantStaff $s) {
            $u = $s->user;

            return [
                'id' => $s->id,
                'name' => $u?->name,
                'email' => $u?->email,
                'phone' => $u?->phone,
                'role' => $s->role,
                'is_active' => (bool) $s->is_active,
                'created_at' => $s->created_at,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Add staff: creates User (login) + MerchantStaff row. Password required for new users.
     */
    public function create(Request $request): JsonResponse
    {
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
            'role' => 'required|in:manager,staff,cashier,scanner,coupon_activation',
            'is_active' => 'sometimes|boolean',
        ]);

        $userRole = Role::where('name', 'user')->firstOrFail();
        $p = $this->permissionsForRole($validated['role']);

        return DB::transaction(function () use ($validated, $merchant, $userRole, $p, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role_id' => $userRole->id,
                'language' => $request->input('language', 'ar'),
                'email_verified_at' => now(),
                'country' => 'مصر',
            ]);

            $staff = MerchantStaff::create([
                'merchant_id' => $merchant->id,
                'user_id' => $user->id,
                'role' => $validated['role'],
                'role_ar' => $p['ar'],
                'role_en' => $p['en'],
                'permissions' => ['coupon_activation' => $p['can_activate_coupons']],
                'can_create_offers' => $p['can_create_offers'],
                'can_edit_offers' => $p['can_edit_offers'],
                'can_activate_coupons' => $p['can_activate_coupons'],
                'can_view_reports' => $p['can_view_reports'],
                'can_manage_staff' => $p['can_manage_staff'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'message' => 'Staff member added successfully',
                'data' => $this->formatStaffRow($staff->load('user')),
            ], 201);
        });
    }

    /**
     * Update staff member (user fields + role + active).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();

        $staff = MerchantStaff::where('merchant_id', $merchant->id)
            ->with('user')
            ->findOrFail($id);

        $user = $staff->user;
        if (! $user) {
            return response()->json(['message' => 'Staff user not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'sometimes|in:manager,staff,cashier,scanner,coupon_activation',
            'is_active' => 'sometimes|boolean',
        ]);

        if (empty($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        return DB::transaction(function () use ($validated, $staff, $user) {
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }
            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }
            if (array_key_exists('phone', $validated)) {
                $user->phone = $validated['phone'];
            }
            if (! empty($validated['password'] ?? null)) {
                $user->password = Hash::make($validated['password']);
            }
            $user->save();

            $staffUpdates = [];
            if (isset($validated['is_active'])) {
                $staffUpdates['is_active'] = $validated['is_active'];
            }
            if (isset($validated['role'])) {
                $p = $this->permissionsForRole($validated['role']);
                $staffUpdates = array_merge($staffUpdates, [
                    'role' => $validated['role'],
                    'role_ar' => $p['ar'],
                    'role_en' => $p['en'],
                    'permissions' => ['coupon_activation' => $p['can_activate_coupons']],
                    'can_create_offers' => $p['can_create_offers'],
                    'can_edit_offers' => $p['can_edit_offers'],
                    'can_activate_coupons' => $p['can_activate_coupons'],
                    'can_view_reports' => $p['can_view_reports'],
                    'can_manage_staff' => $p['can_manage_staff'],
                ]);
            }

            if ($staffUpdates !== []) {
                $staff->update($staffUpdates);
            }

            return response()->json([
                'message' => 'Staff member updated successfully',
                'data' => $this->formatStaffRow($staff->fresh()->load('user')),
            ]);
        });
    }

    /**
     * Soft-remove staff (deactivate link).
     */
    public function delete(string $id): JsonResponse
    {
        $merchant = Merchant::where('user_id', request()->user()->id)->firstOrFail();

        $staff = MerchantStaff::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        $staff->update(['is_active' => false]);

        return response()->json([
            'message' => 'Staff member removed successfully',
        ]);
    }

    /**
     * Paginated activation history for a staff member (merchant owner only).
     */
    public function activations(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();

        $staff = MerchantStaff::where('merchant_id', $merchant->id)
            ->with('user')
            ->findOrFail($id);

        if (! Schema::hasColumn('activation_reports', 'activated_by_user_id')) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
            ]);
        }

        $perPage = min(50, max(5, (int) $request->get('per_page', 15)));

        $paginator = ActivationReport::query()
            ->where('merchant_id', $merchant->id)
            ->where('activated_by_user_id', $staff->user_id)
            ->with(['coupon.offer'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(function (ActivationReport $row) {
            $c = $row->coupon;

            return [
                'id' => $row->id,
                'created_at' => $row->created_at?->toIso8601String(),
                'activation_method' => $row->activation_method,
                'coupon_code' => $c?->coupon_code,
                'coupon_title' => $c && $c->offer
                    ? ($c->offer->title_ar ?? $c->offer->title_en ?? $c->offer->title)
                    : null,
                'price' => $c ? (float) $c->price : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    protected function formatStaffRow(MerchantStaff $s): array
    {
        $u = $s->user;

        $row = [
            'id' => $s->id,
            'name' => $u?->name,
            'email' => $u?->email,
            'phone' => $u?->phone,
            'role' => $s->role,
            'is_active' => (bool) $s->is_active,
            'created_at' => $s->created_at,
        ];

        return array_merge($row, $this->activationSummaryForUser($u?->id, $s->merchant_id));
    }

    /**
     * @return array{activations_total: int, activations_today: int, activations_week: int}
     */
    protected function activationSummaryForUser(?int $userId, int $merchantId): array
    {
        if (! $userId || ! Schema::hasColumn('activation_reports', 'activated_by_user_id')) {
            return [
                'activations_total' => 0,
                'activations_today' => 0,
                'activations_week' => 0,
            ];
        }

        $today = now()->startOfDay();
        $week = now()->startOfWeek();
        $q = ActivationReport::where('merchant_id', $merchantId)->where('activated_by_user_id', $userId);

        return [
            'activations_total' => (clone $q)->count(),
            'activations_today' => (clone $q)->where('created_at', '>=', $today)->count(),
            'activations_week' => (clone $q)->where('created_at', '>=', $week)->count(),
        ];
    }
}
