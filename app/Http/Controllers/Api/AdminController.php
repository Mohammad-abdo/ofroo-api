<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\CouponResource;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Offer;
use App\Services\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function __construct(
        protected ?OfferService $offerService = null
    ) {
        $this->offerService = $this->offerService ?? app(OfferService::class);
    }
    /**
     * List users
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::with('role')
            ->when($request->has('role') && $request->role, function ($query) use ($request) {
                $query->whereHas('role', function ($q) use ($request) {
                    $q->where('name', $request->role);
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
     * List merchants - returns ALL merchants when param "approved" is not sent.
     * When "approved" is sent (1/0 or true/false), filter by that. Never filter by status.
     */
    public function merchants(Request $request): JsonResponse
    {
        try {
            $query = Merchant::with(['user']);

            // فلتر الموافقة فقط عند إرسال المعامل صراحة (عند عدم الإرسال = عرض الكل)
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

            $merchants = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $data = $merchants->getCollection()->map(function ($merchant) {
                $isSuspended = $merchant->suspended_until && $merchant->suspended_until->isFuture();
                $isActive = ! ($merchant->is_blocked ?? false) && ! $isSuspended;

                return [
                    'id' => $merchant->id,
                    'company_name' => $merchant->company_name ?? 'N/A',
                    'company_name_ar' => $merchant->company_name_ar,
                    'company_name_en' => $merchant->company_name_en,
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
                    'total_offers' => $merchant->offers()->count(),
                    'created_coupons_count' => \App\Models\Coupon::whereHas('offer', fn ($q) => $q->where('merchant_id', $merchant->id))->count(),
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
            \Log::error('Error in merchants endpoint: ' . $e->getMessage());

            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get merchant details
     */
    public function getMerchant(string $id): JsonResponse
    {
        $merchant = Merchant::with(['user', 'branches', 'offers'])
            ->findOrFail($id);

        // Count coupons created by this merchant (through offers)
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
            ],
        ]);
    }

    /**
     * Create merchant (Admin)
     */
    public function createMerchant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'required|string|max:255',
            'company_name_ar' => 'nullable|string|max:255',
            'company_name_en' => 'nullable|string|max:255',
            'commercial_registration' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'language' => 'nullable|in:ar,en',
            'city' => 'required|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchantRole = \App\Models\Role::where('name', 'merchant')->firstOrFail();

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'role_id' => $merchantRole->id,
            'language' => $request->language ?? 'ar',
            'city' => $request->city,
            'country' => 'مصر',
        ]);

        // Create merchant
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'company_name' => $request->company_name,
            'company_name_ar' => $request->company_name_ar,
            'company_name_en' => $request->company_name_en,
            'commercial_registration' => $request->commercial_registration,
            'tax_number' => $request->tax_number,
            'city' => $request->city,
            'country' => 'مصر',
            'approved' => $request->is_approved ?? false,
        ]);

        return response()->json([
            'message' => 'Merchant created successfully',
            'data' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
                'user' => new UserResource($user->load('role')),
            ],
        ], 201);
    }

    /**
     * Update merchant (Admin)
     */
    public function updateMerchant(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::with('user')->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $merchant->user_id,
            'phone' => 'sometimes|string|max:20',
            'company_name' => 'sometimes|string|max:255',
            'company_name_ar' => 'sometimes|string|max:255',
            'company_name_en' => 'sometimes|string|max:255',
            'commercial_registration' => 'sometimes|string|max:255',
            'tax_number' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
            'is_approved' => 'sometimes|boolean',
        ]);

        // Update user
        if ($merchant->user) {
            $userData = [];
            if ($request->has('name')) $userData['name'] = $request->name;
            if ($request->has('email')) $userData['email'] = $request->email;
            if ($request->has('phone')) $userData['phone'] = $request->phone;
            if ($request->has('city')) $userData['city'] = $request->city;
            $userData['country'] = 'مصر'; // Always Egypt
            $merchant->user->update($userData);
        }

        // Update merchant
        $merchantData = [];
        if ($request->has('company_name')) $merchantData['company_name'] = $request->company_name;
        if ($request->has('company_name_ar')) $merchantData['company_name_ar'] = $request->company_name_ar;
        if ($request->has('company_name_en')) $merchantData['company_name_en'] = $request->company_name_en;
        if ($request->has('commercial_registration')) $merchantData['commercial_registration'] = $request->commercial_registration;
        if ($request->has('tax_number')) $merchantData['tax_number'] = $request->tax_number;
        if ($request->has('city')) $merchantData['city'] = $request->city;
        if ($request->has('is_approved')) $merchantData['approved'] = $request->is_approved;
        $merchantData['country'] = 'مصر'; // Always Egypt
        $merchant->update($merchantData);

        return response()->json([
            'message' => 'Merchant updated successfully',
            'data' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
            ],
        ]);
    }

    /**
     * Delete merchant (Admin)
     */
    public function deleteMerchant(string $id): JsonResponse
    {
        $merchant = Merchant::with('user')->findOrFail($id);

        // Check if merchant has orders
        if ($merchant->orders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete merchant with existing orders',
            ], 422);
        }

        // Delete merchant and associated user
        if ($merchant->user) {
            $merchant->user->delete();
        }
        $merchant->delete();

        return response()->json([
            'message' => 'Merchant deleted successfully',
        ]);
    }

    /**
     * Approve merchant
     */
    public function approveMerchant(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->update(['approved' => true]);

        // TODO: Send notification to merchant
        // dispatch(new SendMerchantApprovedNotificationJob($merchant));

        return response()->json([
            'message' => 'Merchant approved successfully',
            'data' => $merchant,
        ]);
    }

    /**
     * Sales report
     */
    public function salesReport(Request $request): JsonResponse
    {
        $query = Order::with(['merchant', 'items.offer.category'])
            ->where('payment_status', 'paid');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->has('merchant')) {
            $query->where('merchant_id', $request->merchant);
        }

        if ($request->has('category')) {
            $query->whereHas('items.offer', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        $orders = $query->get();

        $report = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'total_coupons_generated' => $orders->sum(function ($order) {
                return $order->coupons()->count();
            }),
            'total_coupons_activated' => $orders->sum(function ($order) {
                return $order->coupons()->where('status', 'activated')->count();
            }),
            'conversion_rate' => $orders->count() > 0
                ? ($orders->sum(function ($order) {
                    return $order->coupons()->where('status', 'activated')->count();
                }) / $orders->sum(function ($order) {
                    return $order->coupons()->count();
                })) * 100
                : 0,
            'by_merchant' => $orders->groupBy('merchant_id')->map(function ($merchantOrders) {
                return [
                    'merchant_id' => $merchantOrders->first()->merchant_id,
                    'merchant_name' => $merchantOrders->first()->merchant->company_name ?? 'N/A',
                    'total_orders' => $merchantOrders->count(),
                    'total_revenue' => $merchantOrders->sum('total_amount'),
                ];
            })->values(),
        ];

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Get settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $settings = Setting::all()->mapWithKeys(function ($setting) {
            return [$setting->key => [
                'value' => $setting->value,
                'type' => $setting->type,
                'description' => $setting->description,
                'description_ar' => $setting->description_ar,
                'description_en' => $setting->description_en,
            ]];
        });

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Upload application logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$request->hasFile('logo')) {
            return response()->json([
                'message' => 'No logo file provided',
            ], 422);
        }

        $file = $request->file('logo');
        
        // Upload logo
        $logoName = 'app_logo_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('settings', $logoName, 'public');
        $logoUrl = asset('storage/' . $path);

        // Update setting in database
        Setting::updateOrCreate(
            ['key' => 'app_logo'],
            [
                'value' => $logoUrl,
                'type' => 'string',
            ]
        );

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo_url' => $logoUrl,
            ],
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        // Support both formats: flat object or settings array
        $settingsToUpdate = [];

        if ($request->has('settings') && is_array($request->settings)) {
            // Format: { settings: [{ key: 'app_name', value: 'OFROO' }, ...] }
            $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'required',
            ]);
            $settingsToUpdate = $request->settings;
        } else {
            // Format: { app_name: 'OFROO', default_language: 'ar', ... }
            $request->validate([
                'app_name' => 'nullable|string|max:255',
                'default_language' => 'nullable|in:ar,en',
                'max_coupons_per_merchant' => 'nullable|integer|min:1',
                'coupon_expiry_days' => 'nullable|integer|min:1',
                'auto_cancel_enabled' => 'nullable|boolean',
                'days_before_cancel' => 'nullable|integer|min:1',
                'grace_period_hours' => 'nullable|integer|min:0',
                'notify_merchant' => 'nullable|boolean',
                'notify_user' => 'nullable|boolean',
                'auto_refund' => 'nullable|boolean',
                'instagram_url' => 'nullable|string|max:500',
                'facebook_url' => 'nullable|string|max:500',
                'twitter_url' => 'nullable|string|max:500',
                'youtube_url' => 'nullable|string|max:500',
                'snapchat_url' => 'nullable|string|max:500',
                'telegram_url' => 'nullable|string|max:500',
                'tiktok_url' => 'nullable|string|max:500',
                'whatsapp_url' => 'nullable|string|max:500',
            ]);

            // Convert flat object to settings array format
            foreach ($request->all() as $key => $value) {
                if (!in_array($key, ['_token', '_method'])) {
                    $settingsToUpdate[] = [
                        'key' => $key,
                        'value' => $value,
                    ];
                }
            }
        }

        foreach ($settingsToUpdate as $settingData) {
            $key = $settingData['key'] ?? $settingData[0] ?? null;
            $value = $settingData['value'] ?? $settingData[1] ?? null;

            if ($key && $value !== null) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => is_array($value)
                            ? json_encode($value)
                            : (is_bool($value) ? ($value ? '1' : '0') : (string)$value),
                        'type' => $settingData['type'] ?? $this->detectSettingType($value),
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * Detect setting type from value
     */
    private function detectSettingType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'array';
        } else {
            return 'string';
        }
    }

    /**
     * Update category order
     */
    public function updateCategoryOrder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.order_index' => 'required|integer',
        ]);

        foreach ($request->categories as $categoryData) {
            \App\Models\Category::where('id', $categoryData['id'])
                ->update(['order_index' => $categoryData['order_index']]);
        }

        return response()->json([
            'message' => 'Category order updated successfully',
        ]);
    }

    /**
     * Get all categories (Admin)
     */
    public function getCategories(Request $request): JsonResponse
    {
        $query = \App\Models\Category::with(['parent', 'children']);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('order_index')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get single category (Admin)
     */
    public function getCategory(string $id): JsonResponse
    {
        $category = \App\Models\Category::with(['parent', 'children', 'offers'])->findOrFail($id);

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Create category (Admin)
     */
    public function createCategory(Request $request): JsonResponse
    {
        $rules = [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'order_index' => 'nullable|integer',
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = 'image|mimes:jpeg,png,jpg,gif,webp|max:2048';
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en ?? $request->name_ar,
            'parent_id' => $request->parent_id,
            'order_index' => (int) ($request->order_index ?? 0),
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $data['image'] = $file->storeAs('categories', $name, 'public');
        }

        $category = \App\Models\Category::create($data);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Update category (Admin)
     */
    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $category = \App\Models\Category::findOrFail($id);

        $rules = [
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'order_index' => 'nullable|integer',
            'remove_image' => 'nullable|string|in:1,true',
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = 'image|mimes:jpeg,png,jpg,gif,webp|max:2048';
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prevent setting parent_id to itself
        if ($request->has('parent_id') && $request->parent_id == $id) {
            return response()->json([
                'message' => 'Category cannot be its own parent',
            ], 422);
        }

        $update = [
            'name_ar' => $request->input('name_ar', $category->name_ar),
            'name_en' => $request->input('name_en', $category->name_en),
            'parent_id' => $request->has('parent_id') ? $request->parent_id : $category->parent_id,
            'order_index' => $request->has('order_index') ? (int) $request->order_index : $category->order_index,
        ];

        // Remove existing image if requested
        if ($request->input('remove_image') === '1' || $request->input('remove_image') === 'true') {
            if ($category->image && ! (str_starts_with($category->image, 'http://') || str_starts_with($category->image, 'https://'))) {
                Storage::disk('public')->delete($category->image);
            }
            $update['image'] = null;
        }

        // Upload new image if provided
        if ($request->hasFile('image')) {
            // Delete old image if exists (storage path only)
            if ($category->image && ! (str_starts_with($category->image, 'http://') || str_starts_with($category->image, 'https://'))) {
                Storage::disk('public')->delete($category->image);
            }
            $file = $request->file('image');
            $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $update['image'] = $file->storeAs('categories', $name, 'public');
        }

        $category->update($update);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Delete category (Admin)
     */
    public function deleteCategory(string $id): JsonResponse
    {
        $category = \App\Models\Category::findOrFail($id);

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories. Please delete subcategories first.',
            ], 422);
        }

        // Check if category has offers
        if ($category->offers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with offers. Please delete or move offers first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * List all offers (for admin approval)
     */
    public function offers(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 500);

        $offers = \App\Models\Offer::with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->when($request->has('status') && $request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('merchant_id') && $request->merchant_id, function ($query) use ($request) {
                $query->where('merchant_id', $request->merchant_id);
            })
            ->when($request->has('category_id') && $request->category_id, function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->when($request->has('search') && $request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('merchant', function ($merchantQuery) use ($search) {
                            $merchantQuery->where('company_name', 'like', "%{$search}%")
                                ->orWhere('company_name_ar', 'like', "%{$search}%")
                                ->orWhere('company_name_en', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => \App\Http\Resources\OfferResource::collection($offers->items()),
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    /**
     * Get single offer (Admin)
     */
    public function getOffer(string $id): JsonResponse
    {
        $offer = \App\Models\Offer::with(['merchant', 'category', 'mall', 'branches', 'coupons'])->findOrFail($id);

        return response()->json([
            'data' => new \App\Http\Resources\OfferResource($offer),
        ]);
    }

    /**
     * Create offer (Admin)
     */
    public function createOffer(Request $request): JsonResponse
    {
        $rules = [
            'merchant_id' => 'required|exists:merchants,id',
            'category_id' => 'required|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:active,expired,disabled,pending',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
        ];

        $contentType = $request->header('Content-Type', '');
        $isMultipart = !empty($contentType) && str_contains(strtolower($contentType), 'multipart/form-data');
        $validationData = $request->all();
        if ($isMultipart && isset($validationData['offer_images'])) {
            unset($validationData['offer_images']);
        }
        if ($isMultipart) {
            $rules['offer_images'] = 'nullable|array';
            $rules['offer_images.*'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120';
        }

        $validator = Validator::make($validationData, $rules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $imageUrls = [];
        if ($request->hasFile('offer_images')) {
            $files = $request->file('offer_images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $image) {
                if ($image && $image->isValid()) {
                    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('offers', $imageName, 'public');
                    $imageUrls[] = asset('storage/' . $imagePath);
                }
            }
        } elseif ($request->has('offer_images') && is_array($request->offer_images)) {
            foreach ($request->offer_images as $img) {
                if (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                    $imageUrls[] = $img;
                }
            }
        }

        $offer = \App\Models\Offer::create([
            'merchant_id' => $request->merchant_id,
            'category_id' => $request->category_id,
            'mall_id' => $request->mall_id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'discount' => $request->discount ?? 0,
            'offer_images' => $imageUrls,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'pending',
        ]);

        if ($request->has('branches') && is_array($request->branches)) {
            $offer->branches()->sync($request->branches);
        }

        // إنشاء الكوبونات المرسلة مع العرض (نسبة الخصم، صورة، إلخ)
        $couponsList = [];
        $rawCoupons = $request->input('coupons');
        if (is_string($rawCoupons)) {
            $decoded = json_decode($rawCoupons, true);
            $couponsList = is_array($decoded) ? $decoded : [];
        } elseif (is_array($rawCoupons)) {
            $couponsList = $rawCoupons;
        }

        $couponImageFiles = [];
        $filesInput = $request->file('coupon_images');
        if (is_array($filesInput)) {
            foreach ($filesInput as $idx => $file) {
                if ($file && $file->isValid()) {
                    $couponImageFiles[(int) $idx] = $file;
                }
            }
        }
        if (empty($couponImageFiles)) {
            $allFiles = $request->allFiles();
            foreach ($allFiles as $key => $file) {
                if (preg_match('/^coupon_images\[(\d+)\]$/', $key, $m) && $file && $file->isValid()) {
                    $couponImageFiles[(int) $m[1]] = $file;
                }
            }
        }
        ksort($couponImageFiles);
        $couponImageFiles = array_values($couponImageFiles);

        foreach ($couponsList as $index => $couponData) {
            if (!is_array($couponData)) {
                continue;
            }
            $imageFile = $couponImageFiles[$index] ?? null;
            $data = [
                'title' => $couponData['title'] ?? '',
                'description' => $couponData['description'] ?? '',
                'price' => (float) ($couponData['price'] ?? 0),
                'discount' => (float) ($couponData['discount'] ?? 0),
                'discount_type' => $couponData['discount_type'] ?? 'percentage',
                'barcode' => $couponData['barcode'] ?? null,
                'image' => $couponData['image'] ?? null,
                'status' => $couponData['status'] ?? 'active',
                'usage_limit' => isset($couponData['usage_limit']) ? max(1, (int) $couponData['usage_limit']) : 1,
            ];
            try {
                $this->offerService->createCouponForOffer($offer, $data, $imageFile);
            } catch (\Throwable $e) {
                \Log::warning('Admin createOffer: failed to create coupon for offer ' . $offer->id . ': ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Offer created successfully',
            'data' => new \App\Http\Resources\OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ], 201);
    }

    /**
     * Update offer (Admin)
     */
    public function updateOffer(Request $request, string $id): JsonResponse
    {
        $offer = \App\Models\Offer::findOrFail($id);

        $rules = [
            'merchant_id' => 'nullable|exists:merchants,id',
            'category_id' => 'nullable|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|in:active,expired,disabled,pending',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
        ];

        if ($request->hasFile('offer_images')) {
            $rules['offer_images'] = 'nullable|array';
            $rules['offer_images.*'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $updateData = $request->only([
            'merchant_id', 'category_id', 'mall_id', 'title', 'description',
            'price', 'discount', 'start_date', 'end_date', 'status',
        ]);

        $imageUrls = [];
        if ($request->hasFile('offer_images')) {
            $files = $request->file('offer_images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $image) {
                if ($image && $image->isValid()) {
                    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('offers', $imageName, 'public');
                    $imageUrls[] = asset('storage/' . $imagePath);
                }
            }
        }
        $inputImages = $request->input('offer_images');
        if (is_array($inputImages)) {
            foreach ($inputImages as $img) {
                if (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                    $imageUrls[] = $img;
                }
            }
        } elseif (is_string($inputImages) && filter_var($inputImages, FILTER_VALIDATE_URL)) {
            $imageUrls[] = $inputImages;
        }
        if (!empty($imageUrls)) {
            $updateData['offer_images'] = $imageUrls;
        }

        $offer->update(array_filter($updateData));

        if ($request->has('branches')) {
            $offer->branches()->sync($request->branches ?? []);
        }

        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => new \App\Http\Resources\OfferResource($offer->fresh()->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ]);
    }

    /**
     * Delete offer (Admin)
     */
    public function deleteOffer(string $id): JsonResponse
    {
        $offer = \App\Models\Offer::findOrFail($id);

        $offer->branches()->detach();
        $offer->coupons()->delete();
        $offer->delete();

        return response()->json([
            'message' => 'Offer deleted successfully',
        ]);
    }

    /**
     * Approve offer
     */
    public function approveOffer(Request $request, string $id): JsonResponse
    {
        $offer = \App\Models\Offer::findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,rejected',
        ]);

        $offer->update([
            'status' => $request->status === 'active' ? 'active' : 'disabled',
        ]);

        return response()->json([
            'message' => 'Offer ' . ($request->status === 'active' ? 'approved' : 'rejected') . ' successfully',
            'data' => new \App\Http\Resources\OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
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

        // Count used coupons (coupons that have been activated/used)
        $usedCouponsCount = $user->coupons()
            ->whereNotNull('activated_at')
            ->orWhere('status', 'used')
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
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'name_ar' => $user->role->name_ar,
                    'name_en' => $user->role->name_en,
                ],
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
                'total_orders' => $user->orders()->count(),
                'total_coupons' => $user->coupons()->count(),
                'used_coupons_count' => $usedCouponsCount,
                'total_reviews' => $user->reviews()->count(),
                'created_at' => $user->created_at->toIso8601String(),
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
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'sometimes|string|unique:users,phone,' . $id,
            'language' => 'sometimes|in:ar,en',
            'role_id' => 'sometimes|exists:roles,id',
            'city' => 'sometimes|string|max:255|in:القاهرة,الجيزة,الإسكندرية,المنصورة,طنطا,أسيوط,الأقصر,أسوان,بورسعيد,السويس,الإسماعيلية,شبرا الخيمة,زقازيق,بنها,كفر الشيخ,دمياط,المنيا,سوهاج,قنا,البحر الأحمر,مطروح,شمال سيناء,جنوب سيناء,الوادي الجديد,البحيرة,الدقهلية,الشرقية,القليوبية,الفيوم,بني سويف',
            'country' => 'sometimes|string|max:100',
            'is_blocked' => 'sometimes|boolean',
        ]);

        $user = User::findOrFail($id);
        $updateData = $request->only(['name', 'email', 'phone', 'language', 'role_id', 'city', 'is_blocked']);
        // Always set country to Egypt
        $updateData['country'] = 'مصر';
        $user->update($updateData);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new \App\Http\Resources\UserResource($user->load('role')),
        ]);
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Anonymize user data (GDPR compliance)
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@deleted.com',
            'phone' => null,
        ]);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Export sales report as CSV
     */
    public function exportSalesReport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Order::with(['merchant', 'items.offer.category'])
            ->where('payment_status', 'paid');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $orders = $query->get();

        $filename = 'sales_report_' . date('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, ['Order ID', 'Date', 'Merchant', 'Total Amount', 'Payment Method', 'Coupons Generated', 'Coupons Activated']);

            // Data
            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->merchant->company_name ?? 'N/A',
                    $order->total_amount,
                    $order->payment_method,
                    $order->coupons()->count(),
                    $order->coupons()->where('status', 'activated')->count(),
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * List withdrawals
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $query = \App\Models\Withdrawal::with(['merchant', 'approver']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('merchant')) {
            $query->where('merchant_id', $request->merchant);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $data = $withdrawals->getCollection()->map(function ($withdrawal) {
            return [
                'id' => $withdrawal->id,
                'merchant_id' => $withdrawal->merchant_id,
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status,
                'bank_account' => $withdrawal->bank_account,
                'bank_name' => $withdrawal->bank_name,
                'account_holder' => $withdrawal->account_holder,
                'merchant' => [
                    'id' => $withdrawal->merchant->id,
                    'company_name' => $withdrawal->merchant->company_name,
                ],
                'approved_at' => $withdrawal->approved_at ? $withdrawal->approved_at->toIso8601String() : null,
                'created_at' => $withdrawal->created_at ? $withdrawal->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    /**
     * Approve withdrawal
     */
    public function approveWithdrawal(Request $request, string $id): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::findOrFail($id);
        $adminId = $request->user()->id;

        $financialService = app(\App\Services\FinancialService::class);
        $financialService->approveWithdrawal($withdrawal, $adminId);

        return response()->json([
            'message' => 'Withdrawal approved successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }

    /**
     * Reject withdrawal
     */
    public function rejectWithdrawal(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $withdrawal = \App\Models\Withdrawal::findOrFail($id);
        $adminId = $request->user()->id;

        $financialService = app(\App\Services\FinancialService::class);
        $financialService->rejectWithdrawal($withdrawal, $adminId, $request->reason);

        return response()->json([
            'message' => 'Withdrawal rejected successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }

    /**
     * Complete withdrawal
     */
    public function completeWithdrawal(string $id): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::findOrFail($id);

        $financialService = app(\App\Services\FinancialService::class);
        $financialService->completeWithdrawal($withdrawal);

        return response()->json([
            'message' => 'Withdrawal completed successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }

    /**
     * Get financial dashboard
     */
    public function financialDashboard(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->endOfMonth()->toDateString());

        // Platform revenue (commissions)
        $platformRevenue = \App\Models\FinancialTransaction::where('transaction_type', 'commission')
            ->where('transaction_flow', 'outgoing')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // Total merchant payouts
        $totalPayouts = \App\Models\Withdrawal::where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->sum('amount');

        // Outstanding balances
        $outstandingBalances = \App\Models\MerchantWallet::sum('balance');

        // Monthly analytics
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $monthlyData[] = [
                'month' => $monthStart->format('Y-m'),
                'revenue' => \App\Models\FinancialTransaction::where('transaction_type', 'commission')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount'),
                'payouts' => \App\Models\Withdrawal::where('status', 'completed')
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->sum('amount'),
            ];
        }

        return response()->json([
            'data' => [
                'platform_revenue' => $platformRevenue,
                'total_payouts' => $totalPayouts,
                'outstanding_balances' => $outstandingBalances,
                'net_profit' => $platformRevenue - $totalPayouts,
                'monthly_analytics' => $monthlyData,
            ],
        ]);
    }

    /**
     * Get activity logs
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $query = \App\Models\ActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->filled('actor_role')) {
            $query->where('actor_role', $request->actor_role);
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        $logs = $query->paginate($request->get('per_page', 50));

        $data = $logs->getCollection()->map(function ($log) {
            $user = $log->user;
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'actor_role' => $log->actor_role,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'created_at' => $log->created_at ? $log->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get payment gateways
     */
    public function paymentGateways(): JsonResponse
    {
        $gateways = \App\Models\PaymentGateway::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'data' => $gateways,
        ]);
    }

    /**
     * Create payment gateway
     */
    public function createPaymentGateway(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:payment_gateways,name',
            'display_name' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $gateway = \App\Models\PaymentGateway::create($request->all());

        return response()->json([
            'message' => 'Payment gateway created successfully',
            'data' => $gateway,
        ], 201);
    }

    /**
     * Update payment gateway
     */
    public function updatePaymentGateway(Request $request, string $id): JsonResponse
    {
        $gateway = \App\Models\PaymentGateway::findOrFail($id);
        $gateway->update($request->all());

        return response()->json([
            'message' => 'Payment gateway updated successfully',
            'data' => $gateway,
        ]);
    }

    /**
     * Get tax settings
     */
    public function taxSettings(): JsonResponse
    {
        $settings = \App\Models\TaxSetting::where('is_active', true)->get();

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update tax settings
     */
    public function updateTaxSettings(Request $request): JsonResponse
    {
        $request->validate([
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country_code' => 'required|string',
        ]);

        $setting = \App\Models\TaxSetting::updateOrCreate(
            ['country_code' => $request->country_code],
            $request->all()
        );

        return response()->json([
            'message' => 'Tax settings updated successfully',
            'data' => $setting,
        ]);
    }

    /**
     * Get activation reports
     */
    public function activationReports(Request $request): JsonResponse
    {
        $query = \App\Models\ActivationReport::with(['coupon', 'merchant', 'user', 'order'])
            ->orderBy('created_at', 'desc');

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $reports = $query->paginate($request->get('per_page', 50));

        $data = $reports->getCollection()->map(function ($report) {
            return [
                'id' => $report->id,
                'coupon_id' => $report->coupon_id,
                'merchant_id' => $report->merchant_id,
                'user_id' => $report->user_id,
                'order_id' => $report->order_id,
                'coupon_code' => $report->coupon ? $report->coupon->code : null,
                'merchant_name' => $report->merchant ? $report->merchant->company_name : null,
                'user_name' => $report->user ? $report->user->name : null,
                'order_total' => $report->order ? $report->order->total_amount : null,
                'activation_type' => $report->activation_type,
                'created_at' => $report->created_at ? $report->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Suspend/Unfreeze merchant
     */
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

        $suspensionService = app(\App\Services\MerchantSuspensionService::class);

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

    /**
     * Get merchant wallet (Admin view)
     */
    public function getMerchantWallet(string $id): JsonResponse
    {
        $merchant = Merchant::findOrFail($id);
        $wallet = \App\Models\MerchantWallet::firstOrCreate(
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
     * Get all coupons (Admin) - coupons inside offers only.
     */
    public function allCoupons(Request $request): JsonResponse
    {
        $query = Coupon::with(['offer']);

        // Only coupons that belong to an offer
        $query->whereNotNull('offer_id');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%")
                    ->orWhereHas('offer', function ($offerQuery) use ($search) {
                        $offerQuery->where('title', 'like', "%{$search}%")
                            ->orWhere('title_ar', 'like', "%{$search}%")
                            ->orWhere('title_en', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('offer_id')) {
            $query->where('offer_id', $request->offer_id);
        }

        if ($request->has('category_id')) {
            $query->whereHas('offer', fn ($q) => $q->where('category_id', $request->category_id));
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CouponResource::collection($coupons->getCollection()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get coupon stats for admin dashboard (total coupons, total offers, recent updates).
     */
    public function couponStats(Request $request): JsonResponse
    {
        $totalCoupons = Coupon::whereNotNull('offer_id')->count();
        $totalOffers = Offer::count();
        $recentCoupons = Coupon::whereNotNull('offer_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $recentOffers = Offer::where('created_at', '>=', now()->subDays(7))->count();

        return response()->json([
            'data' => [
                'total_coupons' => $totalCoupons,
                'total_offers' => $totalOffers,
                'recent_coupons' => $recentCoupons,
                'recent_offers' => $recentOffers,
            ],
        ]);
    }

    /**
     * Get single coupon (Admin)
     */
    public function getCoupon(string $id): JsonResponse
    {
        $coupon = Coupon::with(['offer'])->findOrFail($id);

        return response()->json([
            'data' => new CouponResource($coupon),
        ]);
    }

    /**
     * Create coupon for an offer (Admin) - same logic as merchant offer coupons.
     */
    public function storeOfferCoupon(Request $request, string $offerId): JsonResponse
    {
        $offer = Offer::findOrFail($offerId);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed,percent,amount',
            'barcode' => 'nullable|string|max:64',
            'image' => 'nullable',
            'status' => 'nullable|in:active,used,expired',
        ]);
        if ($request->hasFile('image')) {
            $validator->addRules(['image' => 'image|mimes:jpeg,png,jpg,gif|max:5120']);
        }
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'price' => (float) $request->price,
            'discount' => (float) ($request->discount ?? 0),
            'discount_type' => in_array($request->discount_type, ['fixed', 'amount'], true) ? 'fixed' : 'percentage',
            'barcode' => $request->barcode ? trim($request->barcode) : null,
            'status' => $request->status ?? 'active',
        ];
        if ($request->has('image') && is_string($request->image)) {
            $data['image'] = $request->image;
        }

        try {
            $coupon = $this->offerService->createCouponForOffer($offer, $data, $request->file('image'));
            return response()->json([
                'message' => 'Coupon created successfully',
                'data' => new CouponResource($coupon->load('offer')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Get coupons by mall (Admin) - via offer.mall_id
     */
    public function getCouponsByMall(Request $request, string $mallId): JsonResponse
    {
        $query = Coupon::with(['offer'])
            ->whereHas('offer', fn ($q) => $q->where('mall_id', $mallId));

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CouponResource::collection($coupons->getCollection()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get coupons by category (Admin) - via offer.category_id
     */
    public function getCouponsByCategory(Request $request, string $categoryId): JsonResponse
    {
        $query = Coupon::with(['offer'])
            ->whereHas('offer', fn ($q) => $q->where('category_id', $categoryId));

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CouponResource::collection($coupons->getCollection()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get available coupons for category and mall (for offer creation)
     */
    public function getAvailableCoupons(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Coupon::query();

        // Filter by category if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by mall if provided
        if ($request->has('mall_id') && $request->mall_id) {
            $query->where('mall_id', $request->mall_id);
        }

        // Only get coupons that are not already assigned to an offer
        $query->whereNull('offer_id');

        // Get coupons with their relationships
        $coupons = $query->with(['category', 'mall'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => CouponResource::collection($coupons),
        ]);
    }

    /**
     * Create coupon (Admin)
     * Coupons must belong to a category and have a usage limit
     */
    public function createCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'mall_id' => 'required|exists:malls,id',
            'coupon_code' => 'nullable|string|unique:coupons,coupon_code',
            'usage_limit' => 'required|integer|min:1',
            'discount_type' => 'required|in:percent,amount',
            'discount_percent' => 'nullable|required_if:discount_type,percent|numeric|min:0|max:100',
            'discount_amount' => 'nullable|required_if:discount_type,amount|numeric|min:0',
            'status' => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired,active,inactive', // Allow old values for backward compatibility
            'expires_at' => 'nullable|date',
            'terms_conditions' => 'nullable|string',
            'is_refundable' => 'nullable|boolean',
            'offer_id' => 'nullable|exists:offers,id',
            'barcode_value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if offer already has a coupon
        if ($request->has('offer_id')) {
            $offer = Offer::findOrFail($request->offer_id);
            if ($offer->coupon_id) {
                return response()->json([
                    'message' => 'This offer already has a coupon. Each offer can only have one coupon.',
                ], 422);
            }
        }

        // Generate coupon code if not provided
        $couponCode = $request->coupon_code ?? 'CPN-' . strtoupper(uniqid());

        // Map old status values to new ones for backward compatibility
        $status = $request->status ?? 'pending';
        if (!in_array($status, ['pending', 'reserved', 'paid', 'activated', 'used', 'cancelled', 'expired'])) {
            // Map old status values
            if ($status === 'active') {
                $status = 'pending';
            } elseif ($status === 'inactive') {
                $status = 'cancelled';
            } else {
                $status = 'pending'; // Default to pending if invalid
            }
        }

        $coupon = Coupon::create([
            'category_id' => $request->category_id,
            'mall_id' => $request->mall_id,
            'offer_id' => $request->offer_id,
            'coupon_code' => $couponCode,
            'barcode_value' => $request->barcode_value ?? $couponCode,
            'usage_limit' => $request->usage_limit,
            'times_used' => 0,
            'status' => $request->status ?? 'pending',
            'expires_at' => $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null,
            'terms_conditions' => $request->terms_conditions,
            'is_refundable' => $request->boolean('is_refundable', false),
            'discount_type' => $request->discount_type ?? 'percent',
            'discount_percent' => $request->discount_type === 'percent' ? $request->discount_percent : null,
            'discount_amount' => $request->discount_type === 'amount' ? $request->discount_amount : null,
            'created_by' => auth()->id(),
            'created_by_type' => 'admin',
        ]);

        // If coupon is assigned to offer, update the offer
        if ($request->has('offer_id')) {
            $offer->update(['coupon_id' => $coupon->id]);
        }

        return response()->json([
            'message' => 'Coupon created successfully',
            'data' => new CouponResource($coupon->load('offer')),
        ], 201);
    }

    /**
     * Update coupon (Admin) - supports both legacy (category/mall) and offer-based (title, price, discount) schema.
     */
    public function updateCoupon(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $rules = [
            'category_id' => 'nullable|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'usage_limit' => 'nullable|integer|min:1',
            'discount_type' => 'nullable|in:percent,amount,percentage,fixed',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired,active',
            'expires_at' => 'nullable|date',
            'terms_conditions' => 'nullable|string',
            'is_refundable' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|unique:coupons,coupon_code,' . $id,
            'barcode_value' => 'nullable|string',
            'offer_id' => 'nullable|exists:offers,id',
            // Offer-based coupon fields (same as merchant)
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:64',
            'image' => 'nullable',
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = 'image|mimes:jpeg,png,jpg,gif|max:5120';
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if new offer already has a coupon
        if ($request->has('offer_id') && $request->offer_id != $coupon->offer_id) {
            $offer = Offer::find($request->offer_id);
            if ($offer && $offer->coupon_id && $offer->coupon_id != $coupon->id) {
                return response()->json([
                    'message' => 'This offer already has a coupon. Each offer can only have one coupon.',
                ], 422);
            }
        }

        $updateData = $request->only([
            'category_id', 'mall_id', 'usage_limit', 'terms_conditions', 'is_refundable',
            'coupon_code', 'barcode_value', 'offer_id',
        ]);

        // Offer-based coupon fields
        if ($request->filled('title')) {
            $updateData['title'] = $request->title;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('price')) {
            $updateData['price'] = (float) $request->price;
        }
        if ($request->has('discount')) {
            $updateData['discount'] = (float) $request->discount;
        }
        if ($request->filled('discount_type')) {
            $dt = $request->discount_type;
            $updateData['discount_type'] = in_array($dt, ['fixed', 'amount'], true) ? 'amount' : 'percent';
        }
        if ($request->filled('barcode')) {
            $updateData['barcode'] = trim($request->barcode);
            if (empty($updateData['coupon_code'])) {
                $updateData['coupon_code'] = $updateData['barcode'];
            }
        }
        if ($request->has('status')) {
            $updateData['status'] = in_array($request->status, ['active', 'used', 'expired'], true)
                ? $request->status
                : ($request->status === 'cancelled' ? 'expired' : $request->status);
        }

        if ($request->has('expires_at')) {
            $updateData['expires_at'] = $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null;
        }

        // Legacy discount fields
        if ($request->has('discount_type') && !isset($updateData['discount_type'])) {
            $dt = $request->discount_type;
            $updateData['discount_type'] = in_array($dt, ['fixed', 'amount'], true) ? 'amount' : 'percent';
        }
        if ($request->has('discount_percent')) {
            $updateData['discount_percent'] = $request->discount_percent;
        }
        if ($request->has('discount_amount')) {
            $updateData['discount_amount'] = $request->discount_amount;
        }

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $path = $request->file('image')->store('coupons', 'public');
            $updateData['image'] = asset('storage/' . $path);
        } elseif ($request->filled('image') && is_string($request->image)) {
            $updateData['image'] = $request->image;
        }

        $coupon->update($updateData);

        if ($request->has('offer_id')) {
            if ($coupon->getOriginal('offer_id') && $coupon->getOriginal('offer_id') != $request->offer_id) {
                $oldOffer = Offer::find($coupon->getOriginal('offer_id'));
                if ($oldOffer) {
                    $oldOffer->update(['coupon_id' => null]);
                }
            }
            $newOffer = Offer::find($request->offer_id);
            if ($newOffer) {
                $newOffer->update(['coupon_id' => $coupon->id]);
            }
        }

        return response()->json([
            'message' => 'Coupon updated successfully',
            'data' => new CouponResource($coupon->load('offer')),
        ]);
    }

    /**
     * Delete coupon (Admin)
     */
    public function deleteCoupon(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        // Check if coupon can be deleted (not activated or used)
        if ($coupon->status === 'activated' || $coupon->status === 'used') {
            return response()->json([
                'message' => 'Cannot delete activated or used coupon',
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully',
        ]);
    }

    /**
     * Activate coupon (Admin)
     */
    public function activateCoupon(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        if ($coupon->status === 'activated') {
            return response()->json([
                'message' => 'Coupon is already activated',
            ], 422);
        }

        if ($coupon->status !== 'active') {
            return response()->json([
                'message' => 'Only active coupons can be activated',
            ], 422);
        }

        $coupon->update([
            'status' => 'activated',
            'activated_at' => now(),
            'activated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Coupon activated successfully',
            'data' => new CouponResource($coupon->load('offer')),
        ]);
    }

    /**
     * Deactivate coupon (Admin)
     */
    public function deactivateCoupon(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        if ($coupon->status === 'inactive') {
            return response()->json([
                'message' => 'Coupon is already inactive',
            ], 422);
        }

        $coupon->update([
            'status' => 'inactive',
        ]);

        return response()->json([
            'message' => 'Coupon deactivated successfully',
            'data' => new CouponResource($coupon->load('offer')),
        ]);
    }

    /**
     * Get merchant warnings (Admin)
     */
    public function getMerchantWarnings(Request $request): JsonResponse
    {
        $query = \App\Models\MerchantWarning::with(['merchant.user', 'admin']);

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('active') && $request->get('active') !== '') {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('warning_type', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('merchant', function ($mq) use ($search) {
                        $mq->where('company_name', 'like', "%{$search}%")
                            ->orWhere('company_name_ar', 'like', "%{$search}%")
                            ->orWhere('company_name_en', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $totalActive = (clone $query)->where('active', true)->count();
        $totalInactive = (clone $query)->where('active', false)->count();

        $warnings = $query->orderBy('issued_at', 'desc')
            ->paginate($request->get('per_page', 50));

        $data = $warnings->getCollection()->map(function ($w) {
            $merchant = $w->merchant;
            $user = $merchant->user ?? null;
            return [
                'id' => $w->id,
                'merchant_id' => $w->merchant_id,
                'warning_type' => $w->warning_type,
                'message' => $w->message,
                'issued_at' => $w->issued_at?->toIso8601String(),
                'expires_at' => $w->expires_at?->toIso8601String(),
                'active' => $w->active,
                'merchant' => $merchant ? [
                    'id' => $merchant->id,
                    'name' => $user->name ?? $merchant->company_name ?? 'N/A',
                    'company_name' => $merchant->company_name,
                    'company_name_ar' => $merchant->company_name_ar,
                    'company_name_en' => $merchant->company_name_en,
                    'email' => $user->email ?? null,
                ] : null,
                'reason' => $w->warning_type,
                'reason_ar' => $w->warning_type,
                'reason_en' => $w->warning_type,
                'description' => $w->message,
                'is_active' => $w->active,
                'created_at' => $w->issued_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $warnings->currentPage(),
                'last_page' => $warnings->lastPage(),
                'per_page' => $warnings->perPage(),
                'total' => $warnings->total(),
                'total_active' => $totalActive,
                'total_inactive' => $totalInactive,
            ],
        ]);
    }

    /**
     * Get user warnings (Admin)
     * Note: Using a generic warnings table structure
     */
    public function getUserWarnings(Request $request): JsonResponse
    {
        // For now, return empty array as UserWarning model doesn't exist
        // This can be implemented when UserWarning model is created
        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
            'message' => 'User warnings feature coming soon',
        ]);
    }

    /**
     * Issue warning to user (Admin)
     */
    public function issueUserWarning(Request $request, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warning_type' => 'required|string|in:violation,spam,abuse,other',
            'message' => 'required|string|min:10|max:1000',
            'expires_at' => 'nullable|date|after:now',
            'severity' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = $request->user();
        $user = User::findOrFail($userId);

        // For now, we'll store warnings in a simple way
        // You can create a UserWarning model later
        // For now, we'll use a notifications or activity log approach

        // Create a warning record (you may need to create UserWarning model)
        // For now, we'll return a success message
        // TODO: Create UserWarning model and table

        return response()->json([
            'message' => 'Warning issued to user successfully',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'warning_type' => $request->warning_type,
                'message' => $request->message,
                'issued_by' => $admin->id,
                'issued_at' => now()->toIso8601String(),
                'expires_at' => $request->expires_at,
                'severity' => $request->severity ?? 'medium',
            ],
        ], 201);
    }

    /**
     * Deactivate user warning (Admin)
     */
    public function deactivateUserWarning(Request $request, string $id): JsonResponse
    {
        // TODO: Implement when UserWarning model is created
        return response()->json([
            'message' => 'User warning deactivated successfully',
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

    /**
     * Block/Unblock merchant
     */
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

        // Also block the associated user
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

    // ==================== Orders Management ====================

    /**
     * Get all orders (Admin)
     */
    public function getOrders(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'merchant', 'items.offer', 'coupons']);

        if ($request->has('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $data = $orders->getCollection()->map(function ($order) {
            return [
                'id' => $order->id,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ],
                'merchant' => [
                    'id' => $order->merchant->id,
                    'company_name' => $order->merchant->company_name,
                ],
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'items_count' => $order->items()->count(),
                'coupons_count' => $order->coupons()->count(),
                'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get single order (Admin)
     */
    public function getOrder(string $id): JsonResponse
    {
        $order = Order::with(['user', 'merchant', 'items.offer', 'coupons', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ],
                'merchant' => [
                    'id' => $order->merchant->id,
                    'company_name' => $order->merchant->company_name,
                ],
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'notes' => $order->notes,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'offer' => [
                            'id' => $item->offer->id,
                            'title_ar' => $item->offer->title_ar,
                            'title_en' => $item->offer->title_en,
                        ],
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                }),
                'coupons' => $order->coupons->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'coupon_code' => $coupon->coupon_code,
                        'status' => $coupon->status,
                    ];
                }),
                'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Create order (Admin)
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'merchant_id' => 'required|exists:merchants,id',
            'items' => 'required|array|min:1',
            'items.*.offer_id' => 'required|exists:offers,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $offer = Offer::findOrFail($itemData['offer_id']);
                $totalAmount += $offer->price * $itemData['quantity'];
            }

            $order = Order::create([
                'user_id' => $request->user_id,
                'merchant_id' => $request->merchant_id,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status ?? 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $offer = Offer::findOrFail($itemData['offer_id']);
                $unitPrice = $offer->price;
                $quantity = $itemData['quantity'];
                $order->items()->create([
                    'offer_id' => $itemData['offer_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'data' => $order->load(['user', 'merchant', 'items']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order (Admin)
     */
    public function updateOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded,cancelled',
            'notes' => 'sometimes|string',
        ]);

        $order->update($request->only(['payment_status', 'notes']));

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => $order->fresh()->load(['user', 'merchant', 'items']),
        ]);
    }

    /**
     * Delete order (Admin)
     */
    public function deleteOrder(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        // Check if order has payments
        if ($order->payments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete order with payments',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Cancel order (Admin)
     */
    public function cancelOrder(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Cannot cancel paid order. Please refund instead.',
            ], 422);
        }

        $order->update(['payment_status' => 'cancelled']);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Refund order (Admin)
     */
    public function refundOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'message' => 'Only paid orders can be refunded',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $order->update([
            'payment_status' => 'refunded',
            'notes' => ($order->notes ?? '') . "\nRefunded: " . $request->reason,
        ]);

        return response()->json([
            'message' => 'Order refunded successfully',
            'data' => $order->fresh(),
        ]);
    }

    // ==================== Payments Management ====================

    /**
     * Get all payments (Admin)
     */
    public function getPayments(Request $request): JsonResponse
    {
        $query = \App\Models\Payment::with(['order.user', 'order.merchant']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('gateway')) {
            $query->where('gateway', $request->gateway);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $payments->getCollection(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get single payment (Admin)
     */
    public function getPayment(string $id): JsonResponse
    {
        $payment = \App\Models\Payment::with(['order.user', 'order.merchant'])
            ->findOrFail($id);

        return response()->json([
            'data' => $payment,
        ]);
    }

    /**
     * Create payment (Admin)
     */
    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'gateway' => 'required|string',
            'status' => 'nullable|in:pending,completed,failed,refunded',
        ]);

        $payment = \App\Models\Payment::create([
            'order_id' => $request->order_id,
            'amount' => $request->amount,
            'gateway' => $request->gateway,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => $payment,
        ], 201);
    }

    /**
     * Update payment (Admin)
     */
    public function updatePayment(Request $request, string $id): JsonResponse
    {
        $payment = \App\Models\Payment::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:pending,completed,failed,refunded',
        ]);

        $payment->update($request->only(['status']));

        return response()->json([
            'message' => 'Payment updated successfully',
            'data' => $payment->fresh(),
        ]);
    }

    /**
     * Delete payment (Admin)
     */
    public function deletePayment(string $id): JsonResponse
    {
        $payment = \App\Models\Payment::findOrFail($id);

        if ($payment->status === 'completed') {
            return response()->json([
                'message' => 'Cannot delete completed payment',
            ], 422);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }

    /**
     * Refund payment (Admin)
     */
    public function refundPayment(Request $request, string $id): JsonResponse
    {
        $payment = \App\Models\Payment::findOrFail($id);

        if ($payment->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed payments can be refunded',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $payment->update(['status' => 'refunded']);

        return response()->json([
            'message' => 'Payment refunded successfully',
            'data' => $payment->fresh(),
        ]);
    }

    // ==================== Transactions Management ====================

    /**
     * Get all transactions (Admin)
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $query = \App\Models\FinancialTransaction::with(['merchant', 'order', 'payment']);

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('flow')) {
            $query->where('transaction_flow', $request->flow);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $transactions->getCollection(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get single transaction (Admin)
     */
    public function getTransaction(string $id): JsonResponse
    {
        $transaction = \App\Models\FinancialTransaction::with(['merchant', 'order', 'payment'])
            ->findOrFail($id);

        return response()->json([
            'data' => $transaction,
        ]);
    }

    /**
     * Create transaction (Admin)
     */
    public function createTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'transaction_type' => 'required|string',
            'transaction_flow' => 'required|in:incoming,outgoing',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $transaction = \App\Models\FinancialTransaction::create([
            'merchant_id' => $request->merchant_id,
            'order_id' => $request->order_id,
            'payment_id' => $request->payment_id,
            'transaction_type' => $request->transaction_type,
            'transaction_flow' => $request->transaction_flow,
            'amount' => $request->amount,
            'description' => $request->description,
            'status' => 'completed',
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Update transaction (Admin)
     */
    public function updateTransaction(Request $request, string $id): JsonResponse
    {
        $transaction = \App\Models\FinancialTransaction::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:pending,completed,failed',
            'description' => 'sometimes|string',
        ]);

        $transaction->update($request->only(['status', 'description']));

        return response()->json([
            'message' => 'Transaction updated successfully',
            'data' => $transaction->fresh(),
        ]);
    }

    /**
     * Delete transaction (Admin)
     */
    public function deleteTransaction(string $id): JsonResponse
    {
        $transaction = \App\Models\FinancialTransaction::findOrFail($id);

        if ($transaction->status === 'completed') {
            return response()->json([
                'message' => 'Cannot delete completed transaction',
            ], 422);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }

    // ==================== Locations Management (Admin) ====================

    /**
     * Get all locations (Admin)
     */
    public function getLocations(Request $request): JsonResponse
    {
        $query = \App\Models\Branch::with(['merchant']);

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        $locations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $locations->getCollection(),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ],
        ]);
    }

    /**
     * Get single location (Admin)
     */
    public function getLocation(string $id): JsonResponse
    {
        $location = \App\Models\Branch::with(['merchant'])
            ->findOrFail($id);

        return response()->json([
            'data' => $location,
        ]);
    }

    /**
     * Create location (Admin)
     */
    public function createLocation(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'address' => 'required|string',
            'address_ar' => 'nullable|string',
            'address_en' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'google_place_id' => 'nullable|string',
            'opening_hours' => 'nullable|array',
        ]);

        $location = \App\Models\Branch::create($request->all());

        return response()->json([
            'message' => 'Location created successfully',
            'data' => $location,
        ], 201);
    }

    /**
     * Update location (Admin)
     */
    public function updateLocation(Request $request, string $id): JsonResponse
    {
        $location = \App\Models\Branch::findOrFail($id);

        $request->validate([
            'address' => 'sometimes|string',
            'address_ar' => 'sometimes|string',
            'address_en' => 'sometimes|string',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
            'google_place_id' => 'sometimes|string',
            'opening_hours' => 'sometimes|array',
        ]);

        $location->update($request->all());

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Delete location (Admin)
     */
    public function deleteLocation(string $id): JsonResponse
    {
        $location = \App\Models\Branch::findOrFail($id);

        // Check if location has offers
        if ($location->offers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location with offers',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully',
        ]);
    }


    // ==================== Staff Management (Admin) ====================
    // Note: This is for admin staff, not merchant staff

    /**
     * Get all staff (Admin)
     */
    public function getStaff(Request $request): JsonResponse
    {
        // For now, return admin users as staff
        $adminRole = \App\Models\Role::where('name', 'admin')->first();

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

        // Verify it's an admin
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
        $adminRole = \App\Models\Role::where('name', 'admin')->firstOrFail();

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
            'email' => 'sometimes|email|unique:users,email,' . $id,
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

        // Prevent deleting yourself
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

    // ==================== Notifications Management ====================

    /**
     * Get all notifications (Admin)
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $query = \App\Models\AdminNotification::with('creator');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('target_audience')) {
            $query->where('target_audience', $request->target_audience);
        }

        if ($request->has('is_sent')) {
            $query->where('is_sent', $request->boolean('is_sent'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $notifications->getCollection(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get single notification (Admin)
     */
    public function getNotification(string $id): JsonResponse
    {
        $notification = \App\Models\AdminNotification::with('creator')
            ->findOrFail($id);

        return response()->json([
            'data' => $notification,
        ]);
    }

    /**
     * Create notification (Admin)
     */
    public function createNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'message' => 'required|string',
            'message_ar' => 'nullable|string',
            'message_en' => 'nullable|string',
            'type' => 'nullable|in:info,success,warning,error,promotion,system',
            'target_audience' => 'required|in:all,users,merchants,admins,specific',
            'target_user_ids' => 'nullable|array',
            'target_merchant_ids' => 'nullable|array',
            'action_url' => 'nullable|string|max:500',
            'action_text' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notification = \App\Models\AdminNotification::create([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'message' => $request->message,
            'message_ar' => $request->message_ar,
            'message_en' => $request->message_en,
            'type' => $request->type ?? 'info',
            'target_audience' => $request->target_audience,
            'target_user_ids' => $request->target_user_ids,
            'target_merchant_ids' => $request->target_merchant_ids,
            'action_url' => $request->action_url,
            'action_text' => $request->action_text,
            'image_url' => $request->image_url,
            'scheduled_at' => $request->scheduled_at,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Notification created successfully',
            'data' => $notification->load('creator'),
        ], 201);
    }

    /**
     * Update notification (Admin)
     */
    public function updateNotification(Request $request, string $id): JsonResponse
    {
        $notification = \App\Models\AdminNotification::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'message_ar' => 'sometimes|string',
            'message_en' => 'sometimes|string',
            'type' => 'sometimes|in:info,success,warning,error,promotion,system',
            'target_audience' => 'sometimes|in:all,users,merchants,admins,specific',
            'target_user_ids' => 'sometimes|array',
            'target_merchant_ids' => 'sometimes|array',
            'action_url' => 'sometimes|string|max:500',
            'action_text' => 'sometimes|string|max:100',
            'image_url' => 'sometimes|string|max:500',
            'scheduled_at' => 'sometimes|date|after:now',
        ]);

        if ($notification->is_sent) {
            return response()->json([
                'message' => 'Cannot update notification that has already been sent',
            ], 422);
        }

        $notification->update($request->all());

        return response()->json([
            'message' => 'Notification updated successfully',
            'data' => $notification->fresh()->load('creator'),
        ]);
    }

    /**
     * Mark notification as read (Admin)
     */
    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $notification = \App\Models\AdminNotification::findOrFail($id);
        
        // إضافة حقل read_at إذا لم يكن موجوداً
        if (!$notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read (Admin)
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        \App\Models\AdminNotification::where('target_audience', 'admins')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete notification (Admin)
     */
    public function deleteNotification(string $id): JsonResponse
    {
        $notification = \App\Models\AdminNotification::findOrFail($id);

        if ($notification->is_sent) {
            return response()->json([
                'message' => 'Cannot delete notification that has already been sent',
            ], 422);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Send notification (Admin)
     */
    public function sendNotification(string $id): JsonResponse
    {
        $notification = \App\Models\AdminNotification::findOrFail($id);

        if ($notification->is_sent) {
            return response()->json([
                'message' => 'Notification has already been sent',
            ], 422);
        }

        // TODO: Implement actual notification sending logic
        // This would send notifications to users/merchants based on target_audience
        // For now, we'll just mark it as sent

        $notification->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification sent successfully',
            'data' => $notification->fresh(),
        ]);
    }

    // ==================== Malls Management ====================

    /**
     * Get all malls (Admin)
     */
    public function getMalls(Request $request): JsonResponse
    {
        $query = \App\Models\Mall::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $malls = $query->orderBy('order_index')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $malls->getCollection(),
            'meta' => [
                'current_page' => $malls->currentPage(),
                'last_page' => $malls->lastPage(),
                'per_page' => $malls->perPage(),
                'total' => $malls->total(),
            ],
        ]);
    }

    /**
     * Get single mall (Admin)
     */
    public function getMall(string $id): JsonResponse
    {
        $mall = \App\Models\Mall::findOrFail($id);

        return response()->json([
            'data' => $mall,
        ]);
    }

    /**
     * Create mall (Admin)
     */
    public function createMall(Request $request): JsonResponse
    {
        // Handle FormData boolean conversion
        $requestData = $request->all();
        if (isset($requestData['is_active'])) {
            $requestData['is_active'] = filter_var($requestData['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // Handle image upload
        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('malls', $imageName, 'public');
            $imageUrl = asset('storage/' . $imagePath);
        } elseif (!empty($requestData['image_url'])) {
            $imageUrl = $requestData['image_url'];
        }

        // Build validation rules conditionally
        $rules = [
            'name' => 'nullable|string|max:255', // Can be derived from name_ar or name_en
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'address' => 'nullable|string|max:500', // Can be derived from address_ar or address_en
            'address_ar' => 'nullable|string|max:500',
            'address_en' => 'nullable|string|max:500',
            'location_ar' => 'nullable|string|max:500', // Frontend sends this
            'location_en' => 'nullable|string|max:500', // Frontend sends this
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'opening_hours' => 'nullable|array',
            'working_hours_ar' => 'nullable|string', // Frontend sends this
            'working_hours_en' => 'nullable|string', // Frontend sends this
            'is_active' => 'nullable|boolean',
            'order_index' => 'nullable|integer',
        ];

        // Only validate image if a file is actually present
        if ($request->hasFile('image')) {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif|max:5120'; // 5MB max
        } else {
            $rules['image'] = 'nullable'; // Allow null/empty if no file
        }

        $validator = Validator::make($requestData, $rules);

        // Custom validation: at least one name is required
        if (empty($requestData['name']) && empty($requestData['name_ar']) && empty($requestData['name_en'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'name' => ['At least one name field (name, name_ar, or name_en) is required.'],
                ],
            ], 422);
        }

        // Custom validation: at least one address is required
        if (
            empty($requestData['address']) && empty($requestData['address_ar']) && empty($requestData['address_en'])
            && empty($requestData['location_ar']) && empty($requestData['location_en'])
        ) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'address' => ['At least one address field is required.'],
                ],
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Map frontend fields to backend fields
        $name = $requestData['name'] ?? $requestData['name_ar'] ?? $requestData['name_en'] ?? '';
        $address = $requestData['address'] ?? $requestData['address_ar'] ?? $requestData['address_en']
            ?? $requestData['location_ar'] ?? $requestData['location_en'] ?? '';
        $addressAr = $requestData['address_ar'] ?? $requestData['location_ar'] ?? null;
        $addressEn = $requestData['address_en'] ?? $requestData['location_en'] ?? null;

        // Handle opening hours
        $openingHours = null;
        if (!empty($requestData['working_hours_ar']) || !empty($requestData['working_hours_en'])) {
            $openingHours = [
                'ar' => $requestData['working_hours_ar'] ?? '',
                'en' => $requestData['working_hours_en'] ?? '',
            ];
        } elseif (!empty($requestData['opening_hours'])) {
            $openingHours = $requestData['opening_hours'];
        }

        $mall = \App\Models\Mall::create([
            'name' => $name,
            'name_ar' => $requestData['name_ar'] ?? null,
            'name_en' => $requestData['name_en'] ?? null,
            'description' => $requestData['description'] ?? null,
            'description_ar' => $requestData['description_ar'] ?? null,
            'description_en' => $requestData['description_en'] ?? null,
            'address' => $address,
            'address_ar' => $addressAr,
            'address_en' => $addressEn,
            'city' => $requestData['city'] ?? 'القاهرة',
            'country' => $requestData['country'] ?? 'مصر',
            'latitude' => $requestData['latitude'] ?? null,
            'longitude' => $requestData['longitude'] ?? null,
            'phone' => $requestData['phone'] ?? null,
            'email' => $requestData['email'] ?? null,
            'website' => $requestData['website'] ?? null,
            'image_url' => $imageUrl,
            'images' => $requestData['images'] ?? null,
            'opening_hours' => $openingHours,
            'is_active' => $requestData['is_active'] ?? true,
            'order_index' => $requestData['order_index'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Mall created successfully',
            'data' => $mall,
        ], 201);
    }

    /**
     * Update mall (Admin)
     */
    public function updateMall(Request $request, string $id): JsonResponse
    {
        $mall = \App\Models\Mall::findOrFail($id);

        // Handle FormData boolean conversion
        $requestData = $request->all();
        if (isset($requestData['is_active'])) {
            $requestData['is_active'] = filter_var($requestData['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('malls', $imageName, 'public');
            $requestData['image_url'] = asset('storage/' . $imagePath);
        }

        // Build validation rules conditionally
        $rules = [
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'description_en' => 'sometimes|string',
            'address' => 'sometimes|string|max:500',
            'address_ar' => 'sometimes|string|max:500',
            'address_en' => 'sometimes|string|max:500',
            'location_ar' => 'sometimes|string|max:500', // Frontend sends this
            'location_en' => 'sometimes|string|max:500', // Frontend sends this
            'city' => 'sometimes|string|max:100',
            'country' => 'sometimes|string|max:100',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'website' => 'sometimes|string|max:500',
            'image_url' => 'sometimes|string|max:500',
            'images' => 'sometimes|array',
            'opening_hours' => 'sometimes|array',
            'working_hours_ar' => 'sometimes|string', // Frontend sends this
            'working_hours_en' => 'sometimes|string', // Frontend sends this
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
        ];

        // Only validate image if a file is actually present
        if ($request->hasFile('image')) {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif|max:5120'; // 5MB max
        } else {
            $rules['image'] = 'nullable'; // Allow null/empty if no file
        }

        $validator = Validator::make($requestData, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Map frontend fields to backend fields
        if (isset($requestData['name_ar']) || isset($requestData['name_en'])) {
            $requestData['name'] = $requestData['name'] ?? $requestData['name_ar'] ?? $requestData['name_en'] ?? $mall->name;
        }
        if (isset($requestData['location_ar']) || isset($requestData['location_en'])) {
            $requestData['address_ar'] = $requestData['address_ar'] ?? $requestData['location_ar'] ?? null;
            $requestData['address_en'] = $requestData['address_en'] ?? $requestData['location_en'] ?? null;
            if (empty($requestData['address'])) {
                $requestData['address'] = $requestData['address_ar'] ?? $requestData['address_en'] ?? $mall->address;
            }
        }

        // Handle opening hours
        if (isset($requestData['working_hours_ar']) || isset($requestData['working_hours_en'])) {
            $requestData['opening_hours'] = [
                'ar' => $requestData['working_hours_ar'] ?? ($mall->opening_hours['ar'] ?? ''),
                'en' => $requestData['working_hours_en'] ?? ($mall->opening_hours['en'] ?? ''),
            ];
        }

        // Remove frontend-specific fields before updating
        unset($requestData['location_ar'], $requestData['location_en'], $requestData['working_hours_ar'], $requestData['working_hours_en']);

        $mall->update($requestData);

        return response()->json([
            'message' => 'Mall updated successfully',
            'data' => $mall->fresh(),
        ]);
    }

    /**
     * Delete mall (Admin)
     */
    public function deleteMall(string $id): JsonResponse
    {
        $mall = \App\Models\Mall::findOrFail($id);
        $mall->delete();

        return response()->json([
            'message' => 'Mall deleted successfully',
        ]);
    }

    // ==================== Ads Management ====================

    /**
     * Get all ads (Admin)
     */
    public function getAds(Request $request): JsonResponse
    {
        $query = \App\Models\Ad::with(['merchant', 'category']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        if ($request->has('ad_type')) {
            $query->where('ad_type', $request->ad_type);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%");
            });
        }

        $ads = $query->orderBy('order_index')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $ads->getCollection(),
            'meta' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }

    /**
     * Get single ad (Admin)
     */
    public function getAd(string $id): JsonResponse
    {
        $ad = \App\Models\Ad::with(['merchant', 'category'])
            ->findOrFail($id);

        return response()->json([
            'data' => $ad,
        ]);
    }

    /**
     * Create ad (Admin)
     */
    public function createAd(Request $request): JsonResponse
    {
        $input = $request->all();
        // Accept "budget" from frontend as total_budget
        if ($request->has('budget') && ! $request->filled('total_budget')) {
            $input['total_budget'] = $request->budget;
        }
        // Treat empty date strings as null so validation passes
        foreach (['start_date', 'end_date'] as $dateField) {
            if (isset($input[$dateField]) && (is_string($input[$dateField]) && trim($input[$dateField]) === '')) {
                $input[$dateField] = null;
            }
        }
        $request->merge($input);
        $validator = Validator::make($input, [
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            // Support both file upload and URL
            'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:10240', // 10MB max
            'video' => 'nullable|file|mimes:mp4,avi,mov,webm|max:51200', // 50MB max for videos
            'image_url' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'link_url' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'ad_type' => 'required|in:banner,popup,sidebar,inline,video',
            'merchant_id' => 'nullable|exists:merchants,id',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable',
            'order_index' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'cost_per_click' => 'nullable|numeric|min:0',
            'total_budget' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            // إضافة دعم الإحصائيات عند الإنشاء
            'views_count' => 'nullable|integer|min:0',
            'clicks_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('start_date') && $request->filled('end_date') && $request->end_date <= $request->start_date) {
            return response()->json([
                'message' => 'End date must be after start date',
            ], 422);
        }

        // Handle file uploads
        $imageUrl = $request->image_url;
        $videoUrl = $request->video_url;

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            // مسار بدون كلمة "ads" لتجنب حجب مانعات الإعلانات (ERR_BLOCKED_BY_CLIENT)
            $imagePath = $imageFile->storeAs('promo/images', $imageName, 'public');
            $imageUrl = asset('storage/' . $imagePath);
        }

        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $videoName = time() . '_' . uniqid() . '.' . $videoFile->getClientOriginalExtension();
            $videoPath = $videoFile->storeAs('promo/videos', $videoName, 'public');
            $videoUrl = asset('storage/' . $videoPath);
        }

        // Validate that we have either image or video based on ad_type
        if ($request->ad_type === 'video' && !$videoUrl) {
            return response()->json([
                'message' => 'Video file or video URL is required for video ads',
            ], 422);
        }

        if (in_array($request->ad_type, ['banner', 'popup', 'sidebar', 'inline']) && !$imageUrl) {
            return response()->json([
                'message' => 'Image file or image URL is required for this ad type',
            ], 422);
        }

        $totalBudget = $request->filled('total_budget') ? $request->total_budget : $request->input('budget');
        $ad = \App\Models\Ad::create([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image_url' => $imageUrl,
            'video_url' => $videoUrl,
            'images' => $request->images,
            'link_url' => $request->link_url,
            'position' => $request->position,
            'ad_type' => $request->ad_type,
            'merchant_id' => $request->merchant_id,
            'category_id' => $request->category_id,
            'is_active' => $request->boolean('is_active', true),
            'order_index' => $request->order_index ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'cost_per_click' => $request->cost_per_click,
            'total_budget' => $totalBudget,
        ]);

        return response()->json([
            'message' => 'Ad created successfully',
            'data' => $ad->load(['merchant', 'category']),
        ], 201);
    }

    /**
     * Update ad (Admin)
     */
    public function updateAd(Request $request, string $id): JsonResponse
    {
        $ad = \App\Models\Ad::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'description_en' => 'sometimes|string',
            // Support both file upload and URL for updates
            'image' => 'sometimes|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'video' => 'sometimes|file|mimes:mp4,avi,mov,webm|max:51200',
            'image_url' => 'sometimes|string|max:500',
            'video_url' => 'sometimes|string|max:500',
            'images' => 'sometimes|array',
            'link_url' => 'sometimes|string|max:500',
            'position' => 'sometimes|string|max:50',
            'ad_type' => 'sometimes|in:banner,popup,sidebar,inline,video',
            'merchant_id' => 'sometimes|exists:merchants,id',
            'category_id' => 'sometimes|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'cost_per_click' => 'sometimes|numeric|min:0',
            'total_budget' => 'sometimes|numeric|min:0',
            // إضافة دعم تحديث الإحصائيات
            'views_count' => 'sometimes|integer|min:0',
            'clicks_count' => 'sometimes|integer|min:0',
        ]);

        $updateData = $request->except(['image', 'video']);

        // Handle file uploads for updates (مسار promo لتجنب حجب مانعات الإعلانات)
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = $imageFile->storeAs('promo/images', $imageName, 'public');
            $updateData['image_url'] = asset('storage/' . $imagePath);
        }

        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $videoName = time() . '_' . uniqid() . '.' . $videoFile->getClientOriginalExtension();
            $videoPath = $videoFile->storeAs('promo/videos', $videoName, 'public');
            $updateData['video_url'] = asset('storage/' . $videoPath);
        }

        $ad->update($updateData);

        return response()->json([
            'message' => 'Ad updated successfully',
            'data' => $ad->fresh()->load(['merchant', 'category']),
        ]);
    }

    /**
     * Delete ad (Admin)
     */
    public function deleteAd(string $id): JsonResponse
    {
        $ad = \App\Models\Ad::findOrFail($id);
        $ad->delete();

        return response()->json([
            'message' => 'Ad deleted successfully',
        ]);
    }

    /**
     * Get all banners (Admin)
     */
    public function getBanners(Request $request): JsonResponse
    {
        $query = \App\Models\Ad::where('ad_type', 'banner');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%");
            });
        }

        $banners = $query->orderBy('order_index')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($banners);
    }

    /**
     * Create banner (Admin)
     */
    public function createBanner(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'link_url' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
            'order_index' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imageUrl = '';
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'banner_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('banners', $fileName, 'public');
            $imageUrl = asset('storage/' . $path);
        }

        $banner = \App\Models\Ad::create([
            'title' => $request->title_en,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image_url' => $imageUrl,
            'link_url' => $request->link_url,
            'position' => $request->position,
            'ad_type' => 'banner',
            'is_active' => $request->boolean('is_active', true),
            'order_index' => $request->order_index ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return response()->json([
            'message' => 'Banner created successfully',
            'data' => $banner,
        ], 201);
    }

    /**
     * Update banner (Admin)
     */
    public function updateBanner(Request $request, string $id): JsonResponse
    {
        $banner = \App\Models\Ad::where('ad_type', 'banner')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description_ar' => 'sometimes|string',
            'description_en' => 'sometimes|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'link_url' => 'sometimes|string|max:500',
            'position' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->except(['image', '_method']);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'banner_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('banners', $fileName, 'public');
            $data['image_url'] = asset('storage/' . $path);
        }
        
        if (isset($data['title_en'])) {
            $data['title'] = $data['title_en'];
        }

        $banner->update($data);

        return response()->json([
            'message' => 'Banner updated successfully',
            'data' => $banner->fresh(),
        ]);
    }

    /**
     * Delete banner (Admin)
     */
    public function deleteBanner(string $id): JsonResponse
    {
        $banner = \App\Models\Ad::where('ad_type', 'banner')->findOrFail($id);
        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted successfully',
        ]);
    }

    // ==================== Activity Logs Additional Methods ====================

    /**
     * Get single activity log (Admin)
     */
    public function getActivityLog(string $id): JsonResponse
    {
        $log = \App\Models\ActivityLog::with('user')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user' => [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ],
                'created_at' => $log->created_at ? $log->created_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Delete activity log (Admin)
     */
    public function deleteActivityLog(string $id): JsonResponse
    {
        $log = \App\Models\ActivityLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'message' => 'Activity log deleted successfully',
        ]);
    }

    /**
     * Clear activity logs (Admin)
     */
    public function clearActivityLogs(): JsonResponse
    {
        $deleted = \App\Models\ActivityLog::where('created_at', '<', now()->subDays(90))->delete();

        return response()->json([
            'message' => 'Activity logs cleared successfully',
            'deleted_count' => $deleted,
        ]);
    }

    // ==================== Payment Gateways Additional Methods ====================

    /**
     * Get single payment gateway (Admin)
     */
    public function getPaymentGateway(string $id): JsonResponse
    {
        $gateway = \App\Models\PaymentGateway::findOrFail($id);

        return response()->json([
            'data' => $gateway,
        ]);
    }

    /**
     * Delete payment gateway (Admin)
     */
    public function deletePaymentGateway(string $id): JsonResponse
    {
        $gateway = \App\Models\PaymentGateway::findOrFail($id);
        $gateway->delete();

        return response()->json([
            'message' => 'Payment gateway deleted successfully',
        ]);
    }

    // ==================== Tax Settings Additional Methods ====================

    /**
     * Create tax setting (Admin)
     */
    public function createTaxSetting(Request $request): JsonResponse
    {
        $request->validate([
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country_code' => 'required|string|unique:tax_settings,country_code',
        ]);

        $setting = \App\Models\TaxSetting::create($request->all());

        return response()->json([
            'message' => 'Tax setting created successfully',
            'data' => $setting,
        ], 201);
    }

    /**
     * Delete tax setting (Admin)
     */
    public function deleteTaxSetting(string $id): JsonResponse
    {
        $setting = \App\Models\TaxSetting::findOrFail($id);
        $setting->delete();

        return response()->json([
            'message' => 'Tax setting deleted successfully',
        ]);
    }

    // ==================== Invoices Additional Methods ====================

    /**
     * Update invoice (Admin)
     */
    public function updateInvoice(Request $request, string $id): JsonResponse
    {
        $invoice = \App\Models\MerchantInvoice::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:draft,issued,paid,cancelled',
            'notes' => 'sometimes|string',
        ]);

        $invoice->update($request->only(['status', 'notes']));

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data' => $invoice->fresh(),
        ]);
    }

    /**
     * Delete invoice (Admin)
     */
    public function deleteInvoice(string $id): JsonResponse
    {
        $invoice = \App\Models\MerchantInvoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Cannot delete paid invoice',
            ], 422);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Get merchants for dropdown/select (Admin) - مبسط للاستخدام في forms
     */
    public function getMerchantsForSelect(Request $request): JsonResponse
    {
        try {
            \Log::info('getMerchantsForSelect called');
            
            $merchants = Merchant::select('id', 'company_name', 'company_name_ar', 'company_name_en')
                ->where('approved', true)
                ->whereNull('is_blocked') // Handle null values
                ->orWhere('is_blocked', false)
                ->orderBy('company_name_ar')
                ->get();

            $data = $merchants->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'name' => $merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name ?? 'تاجر #' . $merchant->id,
                    'company_name' => $merchant->company_name ?? '',
                    'company_name_ar' => $merchant->company_name_ar ?? '',
                    'company_name_en' => $merchant->company_name_en ?? '',
                ];
            });

            \Log::info('getMerchantsForSelect successful, returning ' . count($data) . ' merchants');

            return response()->json([
                'data' => $data,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getMerchantsForSelect: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error fetching merchants for select',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    // ==================== Routes منقولة من التاجر لتوحيد المنطق ====================

    /**
     * Get mall coupons (Admin) - منقول من التاجر
     */
    public function getMallCoupons(Request $request): JsonResponse
    {
        $query = \App\Models\MallCoupon::with(['category', 'mall']);

        // Apply filters if needed
        if ($request->has('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $coupons = $query->paginate($perPage);

        return response()->json([
            'data' => $coupons->items(),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get coupon activations (Admin) - منقول من التاجر
     */
    public function getCouponActivations(Request $request): JsonResponse
    {
        $query = \App\Models\CouponActivation::with(['coupon', 'user', 'merchant', 'order']);

        // Apply filters if needed
        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('coupon_id')) {
            $query->where('coupon_id', $request->coupon_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 15);
        $activations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $activations->items(),
            'meta' => [
                'current_page' => $activations->currentPage(),
                'last_page' => $activations->lastPage(),
                'per_page' => $activations->perPage(),
                'total' => $activations->total(),
            ],
        ]);
    }
}