<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfferRequest;
use App\Http\Requests\OfferStoreRequest;
use App\Http\Requests\OfferUpdateRequest;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Requests\MerchantProfileRequest;
use App\Http\Resources\OfferResource;
use App\Services\OfferService;
use App\Services\MerchantStatisticsService;
use App\Services\MerchantProfileService;
use App\Models\ActivationReport;
use App\Models\Ad;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Helpers\StorageHelper;

class MerchantController extends Controller
{
    public function __construct(
        protected OfferService $offerService,
        protected MerchantStatisticsService $statisticsService,
        protected MerchantProfileService $profileService
    ) {}

    /**
     * Get merchant offers
     */
    public function offers(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = Offer::with(['category', 'mall', 'branches', 'coupons'])
            ->where('merchant_id', $merchant->id);

        // Apply filters if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%");
            });
        }

        $offers = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => OfferResource::collection($offers->items()),
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    /**
     * Get single offer (merchant's own).
     */
    public function getOffer(Request $request, string $id): JsonResponse
    {
        $merchant = $request->user()->merchant;
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 403);
        }
        $offer = Offer::where('merchant_id', $merchant->id)
            ->with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->findOrFail($id);
        return response()->json([
            'data' => new OfferResource($offer),
        ]);
    }

    /**
     * Create offer (new schema: title, offer_images, branches, coupons, etc.)
     */
    public function createOffer(OfferStoreRequest $request): JsonResponse
    {
        $merchant = $request->user()->merchant;
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 403);
        }

        $data = $this->prepareMerchantOfferData($request);
        $data['merchant_id'] = $merchant->id;

        $offer = $this->offerService->createOffer($data);

        return response()->json([
            'message' => 'Offer created successfully',
            'data' => new OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ], 201);
    }

    /**
     * Update offer (new schema)
     */
    public function updateOffer(OfferUpdateRequest $request, string $id): JsonResponse
    {
        $merchant = $request->user()->merchant;
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 403);
        }

        $offer = Offer::where('merchant_id', $merchant->id)->findOrFail($id);
        $data = $this->prepareMerchantOfferData($request);

        $offer = $this->offerService->updateOffer($offer, $data);

        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => new OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ]);
    }

    /**
     * Prepare offer data for create/update: upload images, attach coupon image files.
     * Reads both offer_images and legacy "images" so data is never lost.
     */
    protected function prepareMerchantOfferData(Request $request): array
    {
        $data = $request->validated();

        // Ensure branches array from request (FormData: branches[]=1&branches[]=2)
        if (!array_key_exists('branches', $data) || !is_array($data['branches'])) {
            $raw = $request->input('branches', $request->branches ?? []);
            $data['branches'] = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
        }
        $data['branches'] = array_values(array_filter(array_map('intval', $data['branches'] ?? [])));

        // Ensure coupons array from request (FormData: coupons = JSON string)
        if (!array_key_exists('coupons', $data) || !is_array($data['coupons'])) {
            $raw = $request->input('coupons', $request->coupons ?? []);
            $data['coupons'] = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        }

        $imageUrls = [];

        // URLs from input (existing images)
        foreach (['offer_images', 'images'] as $key) {
            $input = $request->input($key, []) ?: [];
            foreach (is_array($input) ? $input : [] as $img) {
                if (is_string($img) && (str_starts_with($img, 'http') || str_starts_with($img, '/'))) {
                    $imageUrls[] = $img;
                }
            }
        }
        // New uploads: offer_images[] then legacy images[]
        foreach (['offer_images', 'images'] as $key) {
            $files = $request->file($key);
            if (!$files) {
                continue;
            }
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $image) {
                if ($image && $image->isValid()) {
                    $path = $image->store('offers', 'public');
                    $imageUrls[] = asset('storage/' . $path);
                }
            }
        }
        if (!empty($imageUrls)) {
            $data['offer_images'] = array_values(array_unique($imageUrls));
        }

        // Coupon images by index: coupon_images[0], coupon_images[1] -> [0=>File, 1=>File]
        $couponImages = $request->file('coupon_images');
        if ($couponImages !== null) {
            $data['coupon_image_files'] = is_array($couponImages) ? array_values($couponImages) : [$couponImages];
        } else {
            $data['coupon_image_files'] = [];
        }

        return $data;
    }

    /**
     * Delete offer (soft delete). Coupons linked to this offer are deleted first so the offer can always be removed.
     */
    public function deleteOffer(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $offer = Offer::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        // Delete all coupons belonging to this offer so the offer can be deleted
        $offer->coupons()->delete();

        $offer->delete();

        return response()->json([
            'message' => 'Offer deleted successfully',
        ]);
    }

    /**
     * Get merchant orders
     */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->first();

        if (! $merchant) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0]], 200);
        }

        $orders = Order::with(['user', 'items.offer'])
            ->where('merchant_id', $merchant->id)
            ->where('payment_status', 'paid')
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Activate coupon (scan barcode flow)
     */
    public function activateCoupon(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $coupon = Coupon::with(['offer'])
            ->whereHas('offer', function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id);
            })
            ->findOrFail($id);

        // Payment validation: in current schema coupons are tied to offers only

        if ($coupon->status !== 'reserved') {
            return response()->json([
                'message' => 'Coupon cannot be activated. Current status: ' . $coupon->status,
            ], 400);
        }

        $coupon->update([
            'status' => 'activated',
            'activated_at' => now(),
        ]);

        // TODO: Send notification to user
        // dispatch(new SendCouponActivatedNotificationJob($coupon));

        return response()->json([
            'message' => 'Coupon activated successfully',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'coupon_code' => $coupon->coupon_code,
                    'status' => $coupon->status,
                    'activated_at' => $coupon->activated_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get merchant store locations
     */
    public function storeLocations(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $locations = $merchant->branches()
            ->orderBy('created_at', 'desc')
            ->get();

        $userReq = $request->user();
        $language = $request->get('language', $userReq ? $userReq->language : null) ?? 'ar';
        return response()->json([
            'data' => $locations->map(function ($location) use ($language) {
                $name = $language === 'ar' ? ($location->name_ar ?: $location->name_en ?: $location->name)
                    : ($location->name_en ?: $location->name_ar ?: $location->name);
                return [
                    'id' => $location->id,
                    'name' => $name,
                    'name_ar' => $location->name_ar,
                    'name_en' => $location->name_en,
                    'mall_id' => $location->mall_id,
                    'phone' => $location->phone,
                    'is_active' => $location->is_active,
                    'lat' => (float) $location->lat,
                    'lng' => (float) $location->lng,
                    'address' => $location->address,
                    'address_ar' => $location->address_ar,
                    'address_en' => $location->address_en,
                    'google_place_id' => $location->google_place_id,
                    'opening_hours' => $location->opening_hours,
                ];
            }),
        ]);
    }

    /**
     * Create store location
     */
    public function createStoreLocation(Request $request): JsonResponse
    {
        $request->validate([
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'address_ar' => 'nullable|string|max:500',
            'address_en' => 'nullable|string|max:500',
            'google_place_id' => 'nullable|string|max:255',
            'opening_hours' => 'nullable|array',
        ]);

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $location = $merchant->branches()->create([
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'phone' => $request->phone,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'address' => $request->address,
            'address_ar' => $request->address_ar,
            'address_en' => $request->address_en,
            'google_place_id' => $request->google_place_id,
            'opening_hours' => $request->opening_hours,
        ]);

        return response()->json([
            'message' => 'Store location created successfully',
            'data' => $location,
        ], 201);
    }

    /**
     * Get single store location (Merchant)
     */
    public function getStoreLocation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $location = $merchant->branches()->findOrFail($id);

        $userReq = $request->user();
        $language = $request->get('language', $userReq ? $userReq->language : null) ?? 'ar';
        $name = $language === 'ar' ? ($location->name_ar ?: $location->name_en ?: $location->name) : ($location->name_en ?: $location->name_ar ?: $location->name);
        return response()->json([
            'data' => [
                'id' => $location->id,
                'name' => $name,
                'name_ar' => $location->name_ar,
                'name_en' => $location->name_en,
                'mall_id' => $location->mall_id,
                'phone' => $location->phone,
                'is_active' => $location->is_active,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'address' => $location->address,
                'address_ar' => $location->address_ar,
                'address_en' => $location->address_en,
                'google_place_id' => $location->google_place_id,
                'opening_hours' => $location->opening_hours,
            ],
        ]);
    }

    /**
     * Update store location (Merchant)
     */
    public function updateStoreLocation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $location = $merchant->branches()->findOrFail($id);

        $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
            'lat' => 'sometimes|numeric|between:-90,90',
            'lng' => 'sometimes|numeric|between:-180,180',
            'address' => 'sometimes|string|max:500',
            'address_ar' => 'sometimes|string|max:500',
            'address_en' => 'sometimes|string|max:500',
            'google_place_id' => 'sometimes|string|max:255',
            'opening_hours' => 'sometimes|array',
        ]);

        $location->update($request->only([
            'name_ar',
            'name_en',
            'phone',
            'is_active',
            'lat',
            'lng',
            'address',
            'address_ar',
            'address_en',
            'google_place_id',
            'opening_hours',
        ]));

        return response()->json([
            'message' => 'Store location updated successfully',
            'data' => [
                'id' => $location->id,
                'name_ar' => $location->name_ar,
                'name_en' => $location->name_en,
                'phone' => $location->phone,
                'is_active' => $location->is_active,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'address' => $location->address,
                'address_ar' => $location->address_ar,
                'address_en' => $location->address_en,
                'google_place_id' => $location->google_place_id,
                'opening_hours' => $location->opening_hours,
            ],
        ]);
    }

    /**
     * Delete store location (Merchant)
     */
    public function deleteStoreLocation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $location = $merchant->branches()->findOrFail($id);

        // Check if location has offers
        if ($location->offers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location with existing offers',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Store location deleted successfully',
        ]);
    }

    /**
     * Create coupon (Merchant)
     * Merchants can create coupons for their category
     */
    public function createCoupon(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'coupon_code' => 'nullable|string|unique:coupons,coupon_code',
            'usage_limit' => 'required|integer|min:1',
            'discount_type' => 'nullable|in:percent,amount',
            'discount_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->discount_type === 'percent' && !$value) {
                        $fail('Discount percent is required when discount type is percent.');
                    }
                },
            ],
            'discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->discount_type === 'amount' && !$value) {
                        $fail('Discount amount is required when discount type is amount.');
                    }
                },
            ],
            'status' => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired',
            'expires_at' => 'nullable|date|after:today',
            'terms_conditions' => 'nullable|string',
            'is_refundable' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate coupon code if not provided
        $couponCode = $request->coupon_code ?? 'CPN-' . strtoupper(uniqid());

        // Ensure status is valid (only pending, reserved, paid, activated, used, cancelled, expired)
        $validStatuses = ['pending', 'reserved', 'paid', 'activated', 'used', 'cancelled', 'expired'];
        $status = $request->status && in_array($request->status, $validStatuses) 
            ? $request->status 
            : 'pending';

        $coupon = Coupon::create([
            'category_id' => $request->category_id,
            'mall_id' => $request->mall_id,
            'coupon_code' => $couponCode,
            'barcode_value' => $request->barcode_value ?? $couponCode,
            'usage_limit' => $request->usage_limit,
            'times_used' => 0,
            'discount_type' => $request->discount_type ?? 'percent',
            'discount_percent' => $request->discount_percent,
            'discount_amount' => $request->discount_amount,
            'status' => $status,
            'expires_at' => $request->expires_at,
            'terms_conditions' => $request->terms_conditions,
            'is_refundable' => $request->boolean('is_refundable', false),
            'created_by' => $merchant->id,
            'created_by_type' => 'merchant',
        ]);

        return response()->json([
            'message' => 'Coupon created successfully',
            'data' => new \App\Http\Resources\CouponResource($coupon->load(['category'])),
        ], 201);
    }

    /**
     * Get merchant coupons
     */
    public function getCoupons(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = Coupon::with(['offer'])
            ->whereHas('offer', fn ($q) => $q->where('merchant_id', $merchant->id));

        if ($request->has('category_id')) {
            $query->whereHas('offer', fn ($q) => $q->where('category_id', $request->category_id));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => \App\Http\Resources\CouponResource::collection($coupons->getCollection()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get single coupon (Merchant)
     */
    public function getCoupon(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $coupon = Coupon::with(['offer'])
            ->whereHas('offer', fn ($q) => $q->where('merchant_id', $merchant->id))
            ->findOrFail($id);

        return response()->json([
            'data' => new \App\Http\Resources\CouponResource($coupon),
        ]);
    }

    /**
     * Update coupon (Merchant): offer-linked coupons use the same schema as admin; legacy pool coupons keep the old rules.
     */
    public function updateCoupon(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $coupon = Coupon::query()
            ->where('id', $id)
            ->where(function ($q) use ($merchant) {
                $q->whereHas('offer', fn ($o) => $o->where('merchant_id', $merchant->id))
                    ->orWhere(function ($q2) use ($merchant) {
                        $q2->where('created_by', $merchant->id)
                            ->where('created_by_type', 'merchant');
                    });
            })
            ->firstOrFail();

        if ($coupon->offer_id) {
            return $this->merchantUpdateOfferLinkedCoupon($request, $coupon, $merchant);
        }

        if ($coupon->status === 'activated' || $coupon->status === 'used') {
            return response()->json([
                'message' => 'Cannot update activated or used coupon',
            ], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'coupon_code' => 'sometimes|string|unique:coupons,coupon_code,' . $id,
            'usage_limit' => 'sometimes|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,active,inactive',
            'expires_at' => 'nullable|date|after:today',
            'terms_conditions' => 'nullable|string',
            'is_refundable' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $coupon->update($request->only([
            'category_id',
            'coupon_code',
            'usage_limit',
            'discount_percent',
            'discount_amount',
            'status',
            'expires_at',
            'terms_conditions',
            'is_refundable',
        ]));

        if ($request->has('barcode_value')) {
            $coupon->update(['barcode_value' => $request->barcode_value]);
        }

        return response()->json([
            'message' => 'Coupon updated successfully',
            'data' => new \App\Http\Resources\CouponResource($coupon->load(['category', 'offer'])),
        ]);
    }

    /**
     * Update offer-linked coupon (same field rules as AdminController::updateCoupon), scoped to merchant offers.
     */
    private function merchantUpdateOfferLinkedCoupon(Request $request, Coupon $coupon, Merchant $merchant): JsonResponse
    {
        $id = (string) $coupon->id;

        $rules = [
            'category_id' => 'nullable|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'usage_limit' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:percent,amount,percentage,fixed',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired,active,inactive',
            'expires_at' => 'nullable|date',
            'terms_conditions' => 'nullable|string',
            'is_refundable' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|unique:coupons,coupon_code,' . $id,
            'barcode_value' => 'nullable|string',
            'offer_id' => 'nullable|exists:offers,id',
            'title' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:64',
            'image' => 'nullable',
            'starts_at' => 'nullable|date',
        ];
        if ($request->hasFile('image')) {
            $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
            $rules['image'] = 'image|mimes:jpeg,png,jpg,gif,webp|max:'.$maxKb;
        }
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('offer_id') && (int) $request->offer_id !== (int) $coupon->offer_id) {
            $newOffer = Offer::where('id', $request->offer_id)
                ->where('merchant_id', $merchant->id)
                ->first();
            if (! $newOffer) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['offer_id' => ['Offer not found or does not belong to your account.']],
                ], 422);
            }
            if ($newOffer->coupon_id && (int) $newOffer->coupon_id !== (int) $coupon->id) {
                return response()->json([
                    'message' => 'This offer already has a coupon. Each offer can only have one coupon.',
                ], 422);
            }
        }

        $updateData = $request->only([
            'category_id', 'mall_id', 'usage_limit', 'terms_conditions', 'is_refundable',
            'coupon_code', 'barcode_value', 'offer_id',
        ]);

        if ($request->filled('title')) {
            $updateData['title'] = $request->title;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        foreach (['title_ar', 'title_en', 'description_ar', 'description_en'] as $bilingualField) {
            if ($request->exists($bilingualField)) {
                $updateData[$bilingualField] = $request->input($bilingualField);
            }
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

        if ($request->has('starts_at')) {
            $updateData['starts_at'] = $request->starts_at ? date('Y-m-d H:i:s', strtotime($request->starts_at)) : null;
        }

        if ($request->has('discount_type') && ! isset($updateData['discount_type'])) {
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
            $updateData['image'] = asset('storage/'.$path);
        } elseif ($request->filled('image') && is_string($request->image)) {
            $updateData['image'] = $request->image;
        }

        $previousOfferId = $coupon->offer_id;

        $coupon->update($updateData);

        if ($request->has('offer_id')) {
            if ($previousOfferId && (int) $previousOfferId !== (int) $request->offer_id) {
                $oldOffer = Offer::find($previousOfferId);
                if ($oldOffer) {
                    $oldOffer->update(['coupon_id' => null]);
                }
            }
            $newOffer = Offer::find($request->offer_id);
            if ($newOffer && (int) $newOffer->merchant_id === (int) $merchant->id) {
                $newOffer->update(['coupon_id' => $coupon->id]);
            }
        }

        return response()->json([
            'message' => 'Coupon updated successfully',
            'data' => new \App\Http\Resources\CouponResource($coupon->load('offer')),
        ]);
    }

    /**
     * Delete coupon (Merchant): offer-linked or legacy pool, same restrictions as admin for activated/used.
     */
    public function deleteCoupon(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $coupon = Coupon::query()
            ->where('id', $id)
            ->where(function ($q) use ($merchant) {
                $q->whereHas('offer', fn ($o) => $o->where('merchant_id', $merchant->id))
                    ->orWhere(function ($q2) use ($merchant) {
                        $q2->where('created_by', $merchant->id)
                            ->where('created_by_type', 'merchant');
                    });
            })
            ->firstOrFail();

        if ($coupon->status === 'activated' || $coupon->status === 'used') {
            return response()->json([
                'message' => 'Cannot delete activated or used coupon',
            ], 422);
        }

        $offer = $coupon->offer_id ? Offer::find($coupon->offer_id) : null;
        $coupon->delete();
        if ($offer && (int) ($offer->coupon_id ?? 0) === (int) $id) {
            $offer->update(['coupon_id' => null]);
        }

        return response()->json([
            'message' => 'Coupon deleted successfully',
        ]);
    }

    /**
     * Get merchant mall coupons (coupons with mall_id)
     */
    public function getMallCoupons(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = Coupon::with(['offer'])
            ->whereHas('offer', fn ($q) => $q->where('merchant_id', $merchant->id));

        // Filter by mall
        if ($request->has('mall') && $request->mall !== 'all') {
            $query->whereHas('offer', fn ($q) => $q->where('mall_id', $request->mall));
        }

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->whereHas('offer', fn ($q) => $q->where('category_id', $request->category));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by coupon code
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('coupon_code', 'like', '%' . $request->search . '%')
                  ->orWhere('barcode_value', 'like', '%' . $request->search . '%');
            });
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => \App\Http\Resources\CouponResource::collection($coupons->getCollection()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Get merchant statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $stats = $this->statisticsService->getStatistics($merchant);

        return $this->success($stats);
    }

    /**
     * Get merchant profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        \Log::info('Get merchant profile - Request', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
        ]);
        
        $merchant = Merchant::with(['user', 'mall', 'category'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        \Log::info('Get merchant profile - Found merchant', [
            'merchant_id' => $merchant->id,
            'user_id' => $merchant->user_id,
            'company_name' => $merchant->company_name,
            'company_name_ar' => $merchant->company_name_ar,
            'company_name_en' => $merchant->company_name_en,
            'city' => $merchant->city,
            'category_id' => $merchant->category_id,
            'logo_url' => $merchant->logo_url,
        ]);

        return response()->json([
            'data' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
                'company_name_ar' => $merchant->company_name_ar,
                'company_name_en' => $merchant->company_name_en,
                'description' => $merchant->description,
                'description_ar' => $merchant->description_ar,
                'description_en' => $merchant->description_en,
                'address' => $merchant->address,
                'address_ar' => $merchant->address_ar,
                'address_en' => $merchant->address_en,
                'phone' => $merchant->phone,
                'whatsapp_number' => $merchant->whatsapp_number,
                'whatsapp_link' => $merchant->whatsapp_link,
                'whatsapp_enabled' => $merchant->whatsapp_enabled,
                'city' => $merchant->city,
                'country' => $merchant->country ?? 'مصر',
                'logo_url' => $merchant->logo_url,
                'category_id' => $merchant->category_id,
                'category' => $merchant->category ? [
                    'id' => $merchant->category->id,
                    'name_ar' => $merchant->category->name_ar,
                    'name_en' => $merchant->category->name_en,
                ] : null,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'mall' => $merchant->mall ? [
                    'id' => $merchant->mall->id,
                    'name' => $merchant->mall->name,
                    'name_ar' => $merchant->mall->name_ar,
                    'name_en' => $merchant->mall->name_en,
                ] : null,
            ],
        ]);
    }

    /**
     * Update merchant profile
     */
    public function updateProfile(MerchantProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        try {
            $logoFile = $request->hasFile('logo') ? $request->file('logo') : null;
            $profileData = $this->profileService->updateProfile(
                $merchant,
                $user,
                $request->validated(),
                $logoFile
            );

            return $this->updated($profileData, 'Profile updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([], $e->getMessage());
        } catch (\Exception $e) {
            return $this->serverError('Failed to update profile', $e->getMessage());
        }
    }

    /**
     * Upload merchant logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
        ]);

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        try {
            $logoUrl = $this->profileService->uploadLogo($merchant, $request->file('logo'));

            return $this->success([
                'logo_url' => $logoUrl,
                'merchant_id' => $merchant->id,
            ], 'Logo uploaded successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([], $e->getMessage());
        } catch (\Exception $e) {
            return $this->serverError('Failed to upload logo', $e->getMessage());
        }
    }

    /**
     * Get merchant notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->notifications();

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('read')) {
            if ($request->boolean('read')) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        // Filter by is_sent (for compatibility with frontend, we'll assume all notifications are sent)
        // The standard notifications table doesn't have is_sent, so we'll ignore this filter

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                  ->orWhere('data', 'like', "%{$search}%");
            });
        }

        // Get paginated notifications
        $perPage = $request->get('per_page', 15);
        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Format notifications to match frontend expectations
        $formattedNotifications = $notifications->getCollection()->map(function ($notification) {
            $data = is_string($notification->data) 
                ? json_decode($notification->data, true) 
                : $notification->data;

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? $data['title_en'] ?? '',
                'title_ar' => $data['title_ar'] ?? $data['title'] ?? '',
                'title_en' => $data['title_en'] ?? $data['title'] ?? '',
                'message' => $data['message'] ?? $data['message_en'] ?? '',
                'message_ar' => $data['message_ar'] ?? $data['message'] ?? '',
                'message_en' => $data['message_en'] ?? $data['message'] ?? '',
                'type' => $data['type'] ?? $notification->type ?? 'info',
                'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                'created_at' => $notification->created_at ? $notification->created_at->toIso8601String() : null,
                'updated_at' => $notification->updated_at ? $notification->updated_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $formattedNotifications,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read for merchant
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mark all unread notifications as read
        $updated = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'data' => [
                'updated_count' => $updated,
            ],
        ]);
    }

    // ==================== Ads Management (Merchant) ====================

    /**
     * Get merchant's ads
     */
    public function getAds(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

            $query = Ad::with(['category'])
                ->where('merchant_id', $merchant->id);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all' && !empty($request->status)) {
                $status = $request->status;
                // Map status values
                $statusMap = [
                    'under_review' => 'under_review',
                    'pending' => 'under_review',
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                ];
                
                if (isset($statusMap[$status])) {
                    $query->where('status', $statusMap[$status]);
                } else {
                    $query->where('status', $status);
                }
            }

            // Filter by is_active
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title_ar', 'like', "%{$search}%")
                        ->orWhere('title_en', 'like', "%{$search}%")
                        ->orWhere('description_ar', 'like', "%{$search}%")
                        ->orWhere('description_en', 'like', "%{$search}%");
                });
            }

            $ads = $query->orderBy('created_at', 'desc')
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
        } catch (\Exception $e) {
            \Log::error('Error in getAds', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch ads',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single ad (Merchant)
     */
    public function getAd(string $id): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $ad = Ad::with(['category'])
            ->where('merchant_id', $merchant->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $ad,
        ]);
    }

    /**
     * Create ad (Merchant)
     */
    public function createAd(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image_url' => 'required|string|max:500',
            'images' => 'nullable|array',
            'link_url' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'ad_type' => 'nullable|in:banner,popup,sidebar,inline',
            'category_id' => 'nullable|exists:categories,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'cost_per_click' => 'nullable|numeric|min:0',
            'total_budget' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ad = Ad::create([
            'merchant_id' => $merchant->id,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en ?? $request->title_ar,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image_url' => $request->image_url,
            'images' => $request->images ?? [],
            'link_url' => $request->link_url,
            'position' => $request->position,
            'ad_type' => $request->ad_type ?? 'banner',
            'category_id' => $request->category_id,
            'is_active' => false, // Requires admin approval
            'order_index' => 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'cost_per_click' => $request->cost_per_click,
            'total_budget' => $request->total_budget,
        ]);

        return response()->json([
            'message' => 'Ad created successfully. Waiting for admin approval.',
            'data' => $ad->load(['category']),
        ], 201);
    }

    /**
     * Update ad (Merchant)
     */
    public function updateAd(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $ad = Ad::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        // If ad is active, merchant can only update certain fields
        if ($ad->is_active) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'link_url' => 'nullable|string|max:500',
            ]);
        } else {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'title_ar' => 'sometimes|required|string|max:255',
                'title_en' => 'nullable|string|max:255',
                'description_ar' => 'nullable|string',
                'description_en' => 'nullable|string',
                'image_url' => 'sometimes|required|string|max:500',
                'images' => 'nullable|array',
                'link_url' => 'nullable|string|max:500',
                'position' => 'sometimes|required|string|max:50',
                'ad_type' => 'nullable|in:banner,popup,sidebar,inline',
                'category_id' => 'nullable|exists:categories,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'cost_per_click' => 'nullable|numeric|min:0',
                'total_budget' => 'nullable|numeric|min:0',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update ad
        $ad->update($request->only([
            'title_ar', 'title_en', 'description_ar', 'description_en',
            'image_url', 'images', 'link_url', 'position', 'ad_type',
            'category_id', 'start_date', 'end_date', 'cost_per_click', 'total_budget'
        ]));

        // If ad was active and merchant updated it, set is_active back to false for admin review
        if ($ad->is_active && $request->hasAny(['title_ar', 'image_url', 'position'])) {
            $ad->update([
                'is_active' => false,
            ]);
        }

        return response()->json([
            'message' => 'Ad updated successfully',
            'data' => $ad->fresh()->load(['category']),
        ]);
    }

    /**
     * Delete ad (Merchant)
     */
    public function deleteAd(string $id): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $ad = Ad::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        $ad->delete();

        return response()->json([
            'message' => 'Ad deleted successfully',
        ]);
    }

    /**
     * Get ad status (Merchant)
     */
    public function getAdStatus(string $id): JsonResponse
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $ad = Ad::where('merchant_id', $merchant->id)
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $ad->id,
                'is_active' => $ad->is_active,
            ],
        ]);
    }

    /**
     * Get coupon activations (Merchant)
     */
    public function getCouponActivations(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        if ($request->filled('coupon_id')) {
            $couponId = (int) $request->coupon_id;
            $owns = Coupon::where('id', $couponId)
                ->whereHas('offer', fn ($o) => $o->where('merchant_id', $merchant->id))
                ->exists();
            if (! $owns) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $query = ActivationReport::with(['coupon', 'user', 'merchant', 'order'])
                ->where('merchant_id', $merchant->id)
                ->where('coupon_id', $couponId);

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

        $query = Coupon::with(['offer'])
            ->whereHas('offer', function ($q) use ($merchant) {
                $q->where('merchant_id', $merchant->id);
            })
            ->where('status', 'used');

        // Filter by date range (use updated_at if activated_at not on coupons)
        if ($request->has('start_date')) {
            $query->where('updated_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('updated_at', '<=', $request->end_date);
        }

        // Filter by offer
        if ($request->has('offer_id')) {
            $query->where('offer_id', $request->offer_id);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('coupon_code', 'like', "%{$search}%")
                  ->orWhere('barcode_value', 'like', "%{$search}%")
                  ->orWhereHas('order.user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $activations = $query->orderBy('activated_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $activations->getCollection()->map(function ($coupon) {
                return [
                    'id' => $coupon->id,
                    'coupon_code' => $coupon->coupon_code,
                    'barcode_value' => $coupon->barcode_value,
                    'activated_at' => $coupon->activated_at ? $coupon->activated_at->toIso8601String() : null,
                    'offer' => $coupon->offer ? [
                        'id' => $coupon->offer->id,
                        'title_ar' => $coupon->offer->title_ar,
                        'title_en' => $coupon->offer->title_en,
                    ] : null,
                    'customer' => ($order = $coupon->order) && $order->user ? $order->user->name : 'N/A',
                    'order_id' => $coupon->order_id,
                ];
            }),
            'meta' => [
                'current_page' => $activations->currentPage(),
                'last_page' => $activations->lastPage(),
                'per_page' => $activations->perPage(),
                'total' => $activations->total(),
            ],
        ]);
    }

    /**
     * Get commissions (Merchant)
     */
    public function getCommissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $stats = $this->statistics($request);
        $statsData = json_decode($stats->getContent(), true)['data'] ?? [];

        return response()->json([
            'data' => [
                'total_sales' => $statsData['total_revenue'] ?? 0,
                'total_commission' => $statsData['total_commission'] ?? 0,
                'net_profit' => $statsData['net_profit'] ?? 0,
                'commission_rate' => 10, // 10% commission
            ],
        ]);
    }

    /**
     * Get commission transactions (Merchant)
     */
    public function getCommissionTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->first();

        if (! $merchant) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
            ], 200);
        }

        // All paid orders (coupons table no longer has order_id after refactor)
        $query = Order::with(['user', 'items.offer'])
            ->where('merchant_id', $merchant->id)
            ->where('payment_status', 'paid');

        // Filter by period
        if ($request->has('period')) {
            switch ($request->period) {
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', now()->subMonth()->month)
                          ->whereYear('created_at', now()->subMonth()->year);
                    break;
            }
        }

        // Search by user name or offer title
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('items.offer', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('title_ar', 'like', "%{$search}%")
                      ->orWhere('title_en', 'like', "%{$search}%");
                });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $transactions = $orders->getCollection()->map(function ($order) {
            $commission = (float) $order->total_amount * 0.10;
            $firstItem = $order->items->first();
            $offer = $firstItem?->offer;
            $offerTitle = $offer ? ($offer->title_ar ?? $offer->title_en ?? $offer->title) : null;
            $user = $order->user;

            return [
                'id' => $order->id,
                'created_at' => $order->created_at?->toIso8601String(),
                'description' => $offerTitle ?? 'N/A',
                'item' => $offerTitle ?? 'N/A',
                'user' => [
                    'id' => $user->id ?? null,
                    'name' => $user->name ?? 'N/A',
                ],
                'customer' => $user->name ?? 'N/A',
                'branch' => $order->location_id ? 'Branch ' . $order->location_id : 'N/A',
                'location' => $order->location_id ? 'Branch ' . $order->location_id : 'N/A',
                'payment_method' => $order->payment_method ?? 'N/A',
                'payment' => $order->payment_method ?? 'N/A',
                'amount' => $order->total_amount,
                'commission' => $commission,
                'status' => 'تم الفعالية / Activated',
                'transaction_type' => 'تم الفعالية / Activated',
            ];
        });

        return response()->json([
            'data' => $transactions,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get commission rates (Merchant)
     */
    public function getCommissionRates(Request $request): JsonResponse
    {
        // Get all categories with their commission rates
        $categories = \App\Models\Category::select('id', 'name_ar', 'name_en')
            ->orderBy('name_ar')
            ->get();

        $rates = $categories->map(function ($category) {
            return [
                'category' => $category->name_ar ?? $category->name_en,
                'category_ar' => $category->name_ar,
                'category_en' => $category->name_en,
                'rate' => '10%', // Default 10% commission for all categories
                'rate_value' => 10,
            ];
        });

        return response()->json([
            'data' => $rates,
        ]);
    }

    // ==================== Routes منقولة من الأدمن لتوحيد المنطق ====================

    /**
     * Store offer coupon (Merchant) — same payload as admin (titles, price, discount, image, usage_limit, dates).
     */
    public function storeOfferCoupon(Request $request, $offerId): JsonResponse
    {
        $offer = Offer::where('id', $offerId)
            ->where('merchant_id', auth()->user()->merchant->id)
            ->firstOrFail();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed,percent,amount',
            'barcode' => 'nullable|string|max:64',
            'image' => 'nullable',
            'status' => 'nullable|in:active,inactive,used,expired,cancelled,pending',
            'usage_limit' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);
        if ($request->hasFile('image')) {
            $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
            $validator->addRules(['image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:'.$maxKb]);
        }
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $titleAr = $request->title_ar ? trim((string) $request->title_ar) : null;
        $titleEn = $request->title_en ? trim((string) $request->title_en) : null;
        $legacyTitle = $request->title ? trim((string) $request->title) : null;
        if (! $titleAr && ! $titleEn && ! $legacyTitle) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['title' => ['Provide title, or title_ar, or title_en.']],
            ], 422);
        }

        $data = [
            'title' => $legacyTitle ?? ($titleAr ?: $titleEn) ?? '',
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'price' => (float) $request->price,
            'discount' => (float) ($request->discount ?? 0),
            'discount_type' => in_array($request->discount_type, ['fixed', 'amount'], true) ? 'fixed' : 'percentage',
            'barcode' => $request->barcode ? trim($request->barcode) : null,
            'status' => $request->status ?? 'active',
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
        ];
        if ($request->has('usage_limit')) {
            $data['usage_limit'] = $request->input('usage_limit');
        }
        if ($request->has('image') && is_string($request->image)) {
            $data['image'] = $request->image;
        }

        try {
            $coupon = $this->offerService->createCouponForOffer($offer, $data, $request->file('image'));

            if (! $offer->coupon_id) {
                $offer->update(['coupon_id' => $coupon->id]);
            }

            return response()->json([
                'message' => 'Coupon created successfully',
                'data' => new \App\Http\Resources\CouponResource($coupon->load('offer')),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }

    /**
     * Get my coupons (Merchant) - منقول من الأدمن (allCoupons معدل)
     */
    public function getMyCoupons(Request $request): JsonResponse
    {
        $query = \App\Models\Coupon::where('merchant_id', auth()->user()->merchant->id)
            ->with(['offer', 'category', 'mall']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        if ($request->has('search')) {
            $query->where('coupon_code', 'like', '%' . $request->search . '%');
        }

        $perPage = $request->get('per_page', 15);
        $coupons = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
     * Get coupons by mall (Merchant) - منقول من الأدمن
     */
    public function getCouponsByMall(Request $request, $mallId): JsonResponse
    {
        $query = \App\Models\Coupon::where('merchant_id', auth()->user()->merchant->id)
            ->where('mall_id', $mallId)
            ->with(['offer', 'category', 'mall']);

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
     * Get coupons by category (Merchant) - منقول من الأدمن
     */
    public function getCouponsByCategory(Request $request, $categoryId): JsonResponse
    {
        $query = \App\Models\Coupon::where('merchant_id', auth()->user()->merchant->id)
            ->where('category_id', $categoryId)
            ->with(['offer', 'category', 'mall']);

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
     * Get available coupons (Merchant) - منقول من الأدمن
     */
    public function getAvailableCoupons(Request $request): JsonResponse
    {
        $query = \App\Models\Coupon::where('merchant_id', auth()->user()->merchant->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereRaw('times_used < usage_limit');
            })
            ->with(['offer', 'category', 'mall']);

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }

        $perPage = $request->get('per_page', 15);
        $coupons = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
     * Deactivate coupon (Merchant) - منقول من الأدمن
     */
    public function deactivateCoupon($id): JsonResponse
    {
        $coupon = \App\Models\Coupon::where('merchant_id', auth()->user()->merchant->id)
            ->findOrFail($id);

        $coupon->status = 'inactive';
        $coupon->deactivated_at = now();
        $coupon->save();

        return response()->json([
            'message' => 'Coupon deactivated successfully',
            'data' => $coupon,
        ]);
    }
}