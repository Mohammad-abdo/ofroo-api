<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\Role;
use App\Models\User;
use App\Services\CommissionRateResolver;
use App\Services\FeatureFlagService;
use App\Services\MerchantSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class MerchantController extends Controller
{
    /**
     * List merchants - returns ALL merchants when param "approved" is not sent.
     */
    public function merchants(Request $request): JsonResponse
    {
        try {
            $query = Merchant::with(['user'])
                ->withCount(['offers']);

            if ($request->has('approved') && $request->get('approved') !== '' && $request->get('approved') !== null) {
                $query->where('approved', $request->boolean('approved'));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                        ->orWhere('company_name_ar', 'like', "%{$search}%")
                        ->orWhere('company_name_en', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->filled('created_from')) {
                $query->whereDate('created_at', '>=', $request->created_from);
            }
            if ($request->filled('created_to')) {
                $query->whereDate('created_at', '<=', $request->created_to);
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('mall_id')) {
                $query->associatedWithMall($request->mall_id);
            }

            $merchants = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $merchantIds = $merchants->pluck('id')->toArray();
            $couponCounts = Coupon::query()
                ->join('offers', 'coupons.offer_id', '=', 'offers.id')
                ->whereIn('offers.merchant_id', $merchantIds)
                ->whereNull('offers.deleted_at')
                ->selectRaw('offers.merchant_id as merchant_id, COUNT(*) as count')
                ->groupBy('offers.merchant_id')
                ->pluck('count', 'merchant_id')
                ->toArray();

            $data = $merchants->getCollection()->map(function ($merchant) use ($couponCounts) {
                $isSuspended = $merchant->suspended_until && $merchant->suspended_until->isFuture();
                $isActive = ! ($merchant->is_blocked ?? false) && ! $isSuspended;

                return [
                    'id' => $merchant->id,
                    'company_name' => $merchant->company_name ?? 'N/A',
                    'company_name_ar' => $merchant->company_name_ar,
                    'company_name_en' => $merchant->company_name_en,
                    'category_id' => $merchant->category_id,
                    'mall_id' => $merchant->mall_id,
                    'description' => $merchant->description,
                    'phone' => $merchant->phone,
                    'address' => $merchant->address,
                    'city' => $merchant->city,
                    'country' => $merchant->country ?? 'مصر',
                    'approved' => $merchant->approved ?? false,
                    'is_approved' => $merchant->approved ?? false,
                    'is_blocked' => $merchant->is_blocked ?? false,
                    'is_active' => $isActive,
                    'user' => $merchant->user ? [
                        'id' => $merchant->user->id,
                        'name' => $merchant->user->name,
                        'email' => $merchant->user->email,
                        'phone' => $merchant->user->phone ?? null,
                    ] : null,
                    'total_offers' => $merchant->offers_count ?? $merchant->offers()->count(),
                    'created_coupons_count' => $couponCounts[$merchant->id] ?? 0,
                    'created_at' => $merchant->created_at ? $merchant->created_at->toIso8601String() : null,
                ];
            });

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $merchants->currentPage(),
                    'last_page' => $merchants->lastPage(),
                    'per_page' => $merchants->perPage(),
                    'total' => $merchants->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in merchants endpoint: '.$e->getMessage());

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    public function getMerchant(string $id): JsonResponse
    {
        $merchant = Merchant::with(['user', 'branches', 'offers'])
            ->findOrFail($id);

        $createdCouponsCount = Coupon::whereHas('offer', function ($query) use ($merchant) {
            $query->where('merchant_id', $merchant->id);
        })->count();

        return response()->json([
            'data' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
                'company_name_ar' => $merchant->company_name_ar,
                'company_name_en' => $merchant->company_name_en,
                'description' => $merchant->description,
                'phone' => $merchant->phone,
                'address' => $merchant->address,
                'city' => $merchant->city,
                'country' => $merchant->country ?? 'مصر',
                'approved' => $merchant->approved,
                'is_blocked' => $merchant->is_blocked ?? false,
                'user' => [
                    'id' => $merchant->user->id,
                    'name' => $merchant->user->name,
                    'email' => $merchant->user->email,
                    'phone' => $merchant->user->phone,
                ],
                'total_offers' => $merchant->offers()->count(),
                'created_coupons_count' => $createdCouponsCount,
                'created_at' => $merchant->created_at ? $merchant->created_at->toIso8601String() : null,
                'commission' => $this->merchantCommissionPayload($merchant),
            ],
        ]);
    }

    public function updateMerchantCommission(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);

        $validated = $request->validate([
            'commission_mode' => 'required|string|in:platform,custom,waived',
            'commission_custom_percent' => 'nullable|numeric|min:0|max:100|required_if:commission_mode,custom',
        ]);

        if ($validated['commission_mode'] === CommissionRateResolver::MODE_CUSTOM) {
            $merchant->commission_mode = CommissionRateResolver::MODE_CUSTOM;
            $merchant->commission_custom_percent = $validated['commission_custom_percent'];
        } elseif ($validated['commission_mode'] === CommissionRateResolver::MODE_WAIVED) {
            $merchant->commission_mode = CommissionRateResolver::MODE_WAIVED;
            $merchant->commission_custom_percent = null;
        } else {
            $merchant->commission_mode = CommissionRateResolver::MODE_PLATFORM;
            $merchant->commission_custom_percent = null;
        }
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Commission settings updated successfully.',
            'message_ar' => 'تم تحديث إعدادات العمولة.',
            'data' => $this->merchantCommissionPayload($merchant->fresh()),
        ]);
    }

    protected function merchantCommissionPayload(Merchant $merchant): array
    {
        return [
            'commission_mode' => $merchant->commission_mode ?? CommissionRateResolver::MODE_PLATFORM,
            'commission_custom_percent' => $merchant->commission_custom_percent !== null
                ? (float) $merchant->commission_custom_percent
                : null,
            'effective_commission_percent' => CommissionRateResolver::effectivePercentDisplay($merchant),
            'platform_default_commission_percent' => round(FeatureFlagService::getCommissionRate() * 100, 2),
        ];
    }

    public function createMerchant(Request $request): JsonResponse
    {
        $input = $request->all();
        foreach (['mall_id', 'category_id'] as $key) {
            if (isset($input[$key]) && $input[$key] === '') {
                $input[$key] = null;
            }
        }
        if (! empty($input['branches']) && is_array($input['branches'])) {
            foreach ($input['branches'] as $i => $b) {
                if (isset($b['mall_id']) && $b['mall_id'] === '') {
                    $input['branches'][$i]['mall_id'] = null;
                }
            }
        }
        $request->merge($input);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
            'language' => 'nullable|in:ar,en',
            'city' => 'nullable|string|max:255',
            'company_name' => 'required|string|max:255',
            'company_name_ar' => 'nullable|string|max:255',
            'company_name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'address_ar' => 'nullable|string|max:500',
            'address_en' => 'nullable|string|max:500',
            'commercial_registration' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'whatsapp_number' => 'nullable|string|max:50',
            'whatsapp_link' => 'nullable|string|max:500',
            'whatsapp_enabled' => 'nullable|boolean',
            'mall_id' => 'nullable|exists:malls,id',
            'category_id' => 'nullable|exists:categories,id',
            'is_approved' => 'nullable|boolean',
            'branches' => 'nullable|array',
            'branches.*.name' => 'required_with:branches|string|max:255',
            'branches.*.name_ar' => 'nullable|string|max:255',
            'branches.*.name_en' => 'nullable|string|max:255',
            'branches.*.address' => 'nullable|string|max:500',
            'branches.*.address_ar' => 'nullable|string|max:500',
            'branches.*.address_en' => 'nullable|string|max:500',
            'branches.*.phone' => 'nullable|string|max:50',
            'branches.*.mall_id' => 'nullable|exists:malls,id',
            'branches.*.lat' => 'nullable|numeric',
            'branches.*.lng' => 'nullable|numeric',
            'branches.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchantRole = Role::where('name', 'merchant')->first();
        if (! $merchantRole) {
            return response()->json([
                'message' => 'Merchant role not found. Please run: php artisan db:seed --class=RoleSeeder',
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'role_id' => $merchantRole->id,
                'language' => $request->language ?? 'ar',
                'city' => $request->city ?? '',
                'country' => 'مصر',
            ]);

            $merchant = Merchant::create([
                'user_id' => $user->id,
                'company_name' => $request->company_name,
                'company_name_ar' => $request->company_name_ar,
                'company_name_en' => $request->company_name_en,
                'description' => $request->description,
                'description_ar' => $request->description_ar,
                'description_en' => $request->description_en,
                'address' => $request->address,
                'address_ar' => $request->address_ar,
                'address_en' => $request->address_en,
                'phone' => $request->phone,
                'whatsapp_number' => $request->whatsapp_number,
                'whatsapp_link' => $request->whatsapp_link,
                'whatsapp_enabled' => $request->boolean('whatsapp_enabled', true),
                'commercial_registration' => $request->commercial_registration,
                'tax_number' => $request->tax_number,
                'city' => $request->city,
                'country' => 'مصر',
                'mall_id' => $request->mall_id,
                'category_id' => $request->category_id,
                'approved' => $request->boolean('is_approved', false),
            ]);

            $branchesData = $request->input('branches', []);
            if (Schema::hasTable('branches') && count($branchesData) > 0) {
                $branchTableHasMallId = Schema::hasColumn('branches', 'mall_id');
                foreach ($branchesData as $b) {
                    try {
                        $payload = [
                            'merchant_id' => $merchant->id,
                            'name' => $b['name'] ?? 'Branch',
                            'name_ar' => $b['name_ar'] ?? null,
                            'name_en' => $b['name_en'] ?? null,
                            'address' => $b['address'] ?? null,
                            'address_ar' => $b['address_ar'] ?? null,
                            'address_en' => $b['address_en'] ?? null,
                            'phone' => $b['phone'] ?? null,
                            'lat' => isset($b['lat']) && $b['lat'] !== '' ? (float) $b['lat'] : 0,
                            'lng' => isset($b['lng']) && $b['lng'] !== '' ? (float) $b['lng'] : 0,
                            'is_active' => isset($b['is_active']) ? (bool) $b['is_active'] : true,
                        ];
                        if ($branchTableHasMallId && ! empty($b['mall_id'])) {
                            $payload['mall_id'] = $b['mall_id'];
                        }
                        Branch::create($payload);
                    } catch (\Throwable $branchEx) {
                        Log::warning('Admin createMerchant: branch create failed: '.$branchEx->getMessage());
                    }
                }
            }

            return response()->json([
                'message' => 'Merchant created successfully',
                'data' => [
                    'id' => $merchant->id,
                    'company_name' => $merchant->company_name,
                    'user' => new UserResource($user->load('role')),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Admin createMerchant: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Server error while creating merchant.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function updateMerchant(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::with('user')->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$merchant->user_id,
            'phone' => 'sometimes|nullable|string|max:50',
            'company_name' => 'sometimes|string|max:255',
            'company_name_ar' => 'sometimes|nullable|string|max:255',
            'company_name_en' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'description_ar' => 'sometimes|nullable|string',
            'description_en' => 'sometimes|nullable|string',
            'address' => 'sometimes|nullable|string|max:500',
            'address_ar' => 'sometimes|nullable|string|max:500',
            'address_en' => 'sometimes|nullable|string|max:500',
            'commercial_registration' => 'sometimes|nullable|string|max:255',
            'tax_number' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'whatsapp_number' => 'sometimes|nullable|string|max:50',
            'whatsapp_link' => 'sometimes|nullable|string|max:500',
            'whatsapp_enabled' => 'sometimes|boolean',
            'mall_id' => 'sometimes|nullable|exists:malls,id',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'is_approved' => 'sometimes|boolean',
        ]);

        if ($merchant->user) {
            $userData = array_filter([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'city' => $request->input('city'),
            ], fn ($v) => $v !== null);
            $userData['country'] = 'مصر';
            $merchant->user->update($userData);
        }

        $merchantData = ['country' => 'مصر'];
        $merchantFields = [
            'company_name', 'company_name_ar', 'company_name_en',
            'description', 'description_ar', 'description_en',
            'address', 'address_ar', 'address_en', 'phone',
            'commercial_registration', 'tax_number', 'city',
            'whatsapp_number', 'whatsapp_link', 'mall_id', 'category_id',
        ];
        foreach ($merchantFields as $key) {
            if ($request->has($key)) {
                $merchantData[$key] = $request->input($key);
            }
        }
        if ($request->has('whatsapp_enabled')) {
            $merchantData['whatsapp_enabled'] = $request->boolean('whatsapp_enabled');
        }
        if ($request->has('is_approved')) {
            $merchantData['approved'] = $request->boolean('is_approved');
        }
        $merchant->update($merchantData);

        return response()->json([
            'message' => 'Merchant updated successfully',
            'data' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
            ],
        ]);
    }

    public function deleteMerchant(string $id): JsonResponse
    {
        $merchant = Merchant::with('user')->findOrFail($id);

        if ($merchant->orders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete merchant with existing orders',
            ], 422);
        }

        if ($merchant->user) {
            $merchant->user->delete();
        }
        $merchant->delete();

        return response()->json([
            'message' => 'Merchant deleted successfully',
        ]);
    }

    public function approveMerchant(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->update(['approved' => true]);

        return response()->json([
            'message' => 'Merchant approved successfully',
            'data' => $merchant,
        ]);
    }

    public function blockMerchant(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'is_blocked' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $merchant = Merchant::with('user')->findOrFail($id);
        $merchant->update([
            'is_blocked' => $request->is_blocked,
        ]);

        if ($merchant->user) {
            $merchant->user->update([
                'is_blocked' => $request->is_blocked,
            ]);
        }

        $message = $request->is_blocked
            ? 'Merchant blocked successfully'
            : 'Merchant unblocked successfully';

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
                'is_blocked' => $merchant->is_blocked,
                'user' => [
                    'id' => $merchant->user->id,
                    'name' => $merchant->user->name,
                    'is_blocked' => $merchant->user->is_blocked,
                ],
            ],
        ]);
    }

    public function suspendMerchant(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:suspend,unfreeze,disable',
            'until_date' => 'nullable|date|after:now',
            'reason' => 'required|string|min:10',
            'freeze_wallet' => 'nullable|boolean',
        ]);

        $admin = $request->user();
        $merchant = Merchant::findOrFail($id);

        $suspensionService = app(MerchantSuspensionService::class);

        if ($request->action === 'suspend') {
            $until = $request->until_date ? now()->parse($request->until_date) : null;
            $suspensionService->suspend(
                $merchant,
                $admin,
                $until,
                $request->reason,
                $request->boolean('freeze_wallet', false)
            );
        } elseif ($request->action === 'unfreeze') {
            $suspensionService->unfreeze($merchant, $admin, $request->reason);
        } else {
            $suspensionService->disable($merchant, $admin, $request->reason);
        }

        return response()->json([
            'message' => "Merchant {$request->action}d successfully",
            'data' => $merchant->fresh(),
        ]);
    }

    public function getMerchantWallet(string $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);
        $wallet = MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
        );

        return response()->json([
            'data' => [
                'merchant_id' => $merchant->id,
                'merchant_name' => $merchant->company_name,
                'balance' => $wallet->balance,
                'reserved_balance' => $wallet->reserved_balance,
                'available_balance' => $wallet->available_balance,
                'currency' => $wallet->currency,
                'is_frozen' => $wallet->is_frozen ?? false,
            ],
        ]);
    }

    /**
     * Get merchants for dropdown/select (Admin)
     */
    public function getMerchantsForSelect(Request $request): JsonResponse
    {
        try {
            Log::info('getMerchantsForSelect called');

            $merchants = Merchant::select('id', 'company_name', 'company_name_ar', 'company_name_en')
                ->where('approved', true)
                ->whereNull('is_blocked')
                ->orWhere('is_blocked', false)
                ->orderBy('company_name_ar')
                ->get();

            $data = $merchants->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'name' => $merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name ?? 'تاجر #'.$merchant->id,
                    'company_name' => $merchant->company_name ?? '',
                    'company_name_ar' => $merchant->company_name_ar ?? '',
                    'company_name_en' => $merchant->company_name_en ?? '',
                ];
            });

            Log::info('getMerchantsForSelect successful, returning '.count($data).' merchants');

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMerchantsForSelect: '.$e->getMessage());

            return response()->json([
                'message' => 'Error fetching merchants for select',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }
}
