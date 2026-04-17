<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiMediaUrl;
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
use App\Models\AppCouponSetting;
use App\Models\Coupon;
use App\Models\Commission;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use App\Services\CommissionRateResolver;
use App\Services\FeatureFlagService;
use App\Services\QrActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Helpers\StorageHelper;
use App\Support\ImageUploadRules;
use App\Http\Controllers\Concerns\ResolvesMerchantPortal;

class MerchantController extends Controller
{
    use ResolvesMerchantPortal;

    public function __construct(
        protected OfferService $offerService,
        protected MerchantStatisticsService $statisticsService,
        protected MerchantProfileService $profileService,
        protected QrActivationService $qrActivationService
    ) {}

    /**
     * Get merchant offers
     */
    public function offers(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $request->user()->merchantForPortal();
        if (! $merchant) {
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
        // Merchant offers require admin approval before going live (and coupons stay pending until then).
        $data['status'] = 'pending_approval';

        $offer = $this->offerService->createOffer($data);

        return response()->json([
            'message' => 'Offer submitted for admin review. It will go live after approval.',
            'data' => new OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ], 201);
    }

    /**
     * Update offer (new schema)
     */
    public function updateOffer(OfferUpdateRequest $request, string $id): JsonResponse
    {
        $this->assertMerchantOwner($request);
        $merchant = $this->resolveMerchant($request);

        $offer = Offer::where('merchant_id', $merchant->id)->findOrFail($id);
        $data = $this->prepareMerchantOfferData($request);
        if (strtolower((string) ($offer->status ?? '')) === 'pending_approval') {
            $data['status'] = 'pending_approval';
        }

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $request->user()->merchantForPortal();

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
     * Manual activation for a wallet entitlement ({id} = coupon_entitlements.id).
     */
    public function activateCoupon(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

        $result = $this->qrActivationService->activateEntitlementById((int) $id, $merchant, $user, [
            'ip_address' => $request->ip(),
            'activation_method' => 'manual',
        ]);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Activation failed',
                'data' => $result['coupon'] ?? null,
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'coupon' => $result['coupon'],
                'entitlement' => isset($result['entitlement'])
                    ? new \App\Http\Resources\CouponEntitlementResource($result['entitlement'])
                    : null,
                'redeem_type' => $result['redeem_type'] ?? null,
            ],
        ]);
    }

    /**
     * Get merchant store locations
     */
    public function storeLocations(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
        $this->assertMerchantOwner($request);
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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

        $isOfferBased = $request->filled('offer_id') && !$request->filled('category_id');

        if ($isOfferBased) {
            $rules = [
                'offer_id'       => 'required|exists:offers,id',
                'title'          => 'nullable|string|max:255',
                'title_ar'       => 'nullable|string|max:255',
                'title_en'       => 'nullable|string|max:255',
                'description'    => 'nullable|string',
                'description_ar' => 'nullable|string',
                'description_en' => 'nullable|string',
                'price'          => 'required|numeric|min:0',
                'discount'       => 'nullable|numeric|min:0',
                'discount_type'  => 'nullable|in:percent,amount,percentage,fixed',
                'barcode'        => 'nullable|string|max:64',
                'coupon_code'    => 'nullable|string|unique:coupons,coupon_code',
                'usage_limit'    => 'nullable|integer|min:0',
                'status'         => 'nullable|in:active,inactive,used,expired,pending',
                'starts_at'      => 'nullable|date',
                'expires_at'     => 'nullable|date',
                'image'          => 'nullable',
            ];
            if ($request->hasFile('image')) {
                $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
                $rules['image'] = ImageUploadRules::fileMax($maxKb);
            }

            $offer = Offer::where('id', $request->offer_id)
                ->where('merchant_id', $merchant->id)
                ->first();
            if (!$offer) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['offer_id' => ['Offer not found or does not belong to your account.']],
                ], 422);
            }
        } else {
            $rules = [
                'category_id'      => 'required|exists:categories,id',
                'mall_id'          => 'nullable|exists:malls,id',
                'coupon_code'      => 'nullable|string|unique:coupons,coupon_code',
                'usage_limit'      => 'required|integer|min:1',
                'discount_type'    => 'nullable|in:percent,amount,percentage,fixed',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'discount_amount'  => 'nullable|numeric|min:0',
                'status'           => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired,active,inactive',
                'expires_at'       => 'nullable|date',
                'terms_conditions' => 'nullable|string',
                'is_refundable'    => 'nullable|boolean',
            ];
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($isOfferBased) {
            $dt = $request->input('discount_type', 'percent');
            $mappedDt = in_array($dt, ['fixed', 'amount'], true) ? 'amount' : 'percent';
            $barcodeVal = $request->input('barcode') ?: ('CPN-' . strtoupper(uniqid()));
            $imagePath = null;
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $imagePath = asset('storage/' . $request->file('image')->store('coupons', 'public'));
            } elseif ($request->filled('image') && is_string($request->image)) {
                $imagePath = $request->image;
            }

            try {
                AppCouponSetting::assertOfferCanAddCoupon($offer);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            $coupon = Coupon::create([
                'offer_id'       => $request->offer_id,
                'coupon_setting_id' => AppCouponSetting::current()->id,
                'title'          => $request->input('title', $request->input('title_ar', '')),
                'title_ar'       => $request->input('title_ar'),
                'title_en'       => $request->input('title_en'),
                'description'    => $request->input('description', $request->input('description_ar', '')),
                'description_ar' => $request->input('description_ar'),
                'description_en' => $request->input('description_en'),
                'price'          => (float) $request->price,
                'discount'       => (float) ($request->discount ?? 0),
                'discount_type'  => $mappedDt,
                'barcode'        => $barcodeVal,
                'coupon_code'    => $request->input('coupon_code', $barcodeVal),
                'usage_limit'    => (int) ($request->usage_limit ?? 0),
                'times_used'     => 0,
                'status'         => $request->input('status', 'active'),
                'starts_at'      => $request->starts_at ? date('Y-m-d H:i:s', strtotime($request->starts_at)) : null,
                'expires_at'     => $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null,
                'image'          => $imagePath,
            ]);

            $offer->update(['coupon_id' => $coupon->id]);
        } else {
            $couponCode = $request->coupon_code ?? 'CPN-' . strtoupper(uniqid());
            $coupon = Coupon::create([
                'coupon_setting_id' => AppCouponSetting::current()->id,
                'category_id'      => $request->category_id,
                'mall_id'          => $request->mall_id,
                'coupon_code'      => $couponCode,
                'barcode_value'    => $request->barcode_value ?? $couponCode,
                'usage_limit'      => $request->usage_limit,
                'times_used'       => 0,
                'discount_type'    => $request->input('discount_type', 'percent'),
                'discount_percent' => $request->discount_percent,
                'discount_amount'  => $request->discount_amount,
                'status'           => $request->input('status', 'pending'),
                'expires_at'       => $request->expires_at,
                'terms_conditions' => $request->terms_conditions,
                'is_refundable'    => $request->boolean('is_refundable', false),
                'created_by'       => $merchant->id,
                'created_by_type'  => 'merchant',
            ]);
        }

        return response()->json([
            'message' => 'Coupon created successfully',
            'data' => new \App\Http\Resources\CouponResource($coupon->load(['offer'])),
        ], 201);
    }

    /**
     * Get merchant coupons
     */
    public function getCoupons(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
            $rules['image'] = ImageUploadRules::fileMax($maxKb);
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
        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

        if ($user->isMerchant()) {
            $stats = $this->statisticsService->getStatistics($merchant);
        } else {
            $stats = $this->statisticsService->getStatisticsForActivator($merchant, $user);
        }

        return $this->success($stats);
    }

    /**
     * Paginated coupon activations performed by the current user (store owner or staff) for this merchant.
     */
    public function myActivationHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

        if (! Schema::hasColumn('activation_reports', 'activated_by_user_id')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
            ]);
        }

        $perPage = min(50, max(5, (int) $request->get('per_page', 10)));

        $paginator = ActivationReport::query()
            ->where('merchant_id', $merchant->id)
            ->where('activated_by_user_id', $user->id)
            ->with(['coupon.offer'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(function (ActivationReport $row) {
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
        })->values();

        return $this->successWithPagination($paginator, $data);
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
        
        $merchant = Merchant::with(['user', 'mall', 'category', 'branches.mall'])
            ->findOrFail($this->resolveMerchant($request)->id);

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
                'logo_url' => ApiMediaUrl::publicAbsolute(is_string($merchant->logo_url) ? $merchant->logo_url : ''),
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
                'mall' => ($mall = $merchant->resolveDisplayMall()) ? [
                    'id' => $mall->id,
                    'name' => $mall->name,
                    'name_ar' => $mall->name_ar,
                    'name_en' => $mall->name_en,
                ] : null,
            ],
        ]);
    }

    /**
     * Update merchant profile
     */
    public function updateProfile(MerchantProfileRequest $request): JsonResponse
    {
        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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
            'logo' => ImageUploadRules::requiredFileMax(2048),
        ]);

        $this->assertMerchantOwner($request);
        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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

    /**
     * Mark single database notification as read (merchant)
     */
    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
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
     * Delete a database notification (merchant inbox)
     */
    public function deleteMerchantNotification(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
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
            $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

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
        $merchant = $this->resolveMerchant($request);

        $stats = $this->statistics($request);
        $statsData = json_decode($stats->getContent(), true)['data'] ?? [];

        $platformPct = round(FeatureFlagService::getCommissionRate() * 100, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $statsData['total_revenue'] ?? 0,
                'total_commission' => $statsData['total_commission'] ?? 0,
                'net_profit' => $statsData['net_profit'] ?? 0,
                'commission_rate_effective_percent' => CommissionRateResolver::effectivePercentDisplay($merchant),
                'commission_mode' => $merchant->commission_mode ?? CommissionRateResolver::MODE_PLATFORM,
                'commission_custom_percent' => $merchant->commission_custom_percent !== null
                    ? (float) $merchant->commission_custom_percent
                    : null,
                'platform_default_commission_percent' => $platformPct,
            ],
        ]);
    }

    /**
     * Get commission transactions (Merchant)
     */
    public function getCommissionTransactions(Request $request): JsonResponse
    {
        $merchant = $request->user()->merchantForPortal();

        if (! $merchant) {
            return response()->json([
                'success' => true,
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

        $orderIds = $orders->getCollection()->pluck('id')->filter()->values();
        $commissionsByOrder = Commission::whereIn('order_id', $orderIds)->get()->keyBy('order_id');

        $transactions = $orders->getCollection()->map(function ($order) use ($commissionsByOrder, $merchant) {
            $row = $commissionsByOrder->get($order->id);
            $commission = $row
                ? (float) $row->commission_amount
                : (float) $order->total_amount * CommissionRateResolver::effectiveDecimalRate($merchant);
            $ratePercent = $row
                ? round((float) $row->commission_rate * 100, 2)
                : CommissionRateResolver::effectivePercentDisplay($merchant);
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
                'amount' => (float) $order->total_amount,
                'commission' => round($commission, 2),
                'commission_rate_percent' => $ratePercent,
                'status' => 'تم الفعالية / Activated',
                'transaction_type' => 'تم الفعالية / Activated',
            ];
        });

        return response()->json([
            'success' => true,
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
        $merchant = $this->resolveMerchant($request);
        $platformPct = round(FeatureFlagService::getCommissionRate() * 100, 2);
        $effective = CommissionRateResolver::effectivePercentDisplay($merchant);

        $category = $merchant->relationLoaded('category') ? $merchant->category : $merchant->load('category')->category;

        $rates = [
            [
                'scope' => 'merchant_effective',
                'label_ar' => 'نسبة العمولة المطبّقة على حسابك',
                'label_en' => 'Your effective commission rate',
                'rate' => $effective.'%',
                'rate_value' => $effective,
            ],
            [
                'scope' => 'platform_default',
                'label_ar' => 'النسبة الافتراضية للمنصة',
                'label_en' => 'Platform default rate',
                'rate' => $platformPct.'%',
                'rate_value' => $platformPct,
            ],
        ];

        if ($category) {
            $rates[] = [
                'scope' => 'merchant_category',
                'label_ar' => 'تصنيف نشاطك (مرجعي)',
                'label_en' => 'Your business category (reference)',
                'category_ar' => $category->name_ar,
                'category_en' => $category->name_en,
                'category' => $category->name_ar ?? $category->name_en,
                'rate' => $platformPct.'%',
                'rate_value' => $platformPct,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $rates,
            'meta' => [
                'commission_mode' => $merchant->commission_mode ?? CommissionRateResolver::MODE_PLATFORM,
                'commission_custom_percent' => $merchant->commission_custom_percent !== null
                    ? (float) $merchant->commission_custom_percent
                    : null,
            ],
        ]);
    }

    // ==================== Routes منقولة من الأدمن لتوحيد المنطق ====================

    /**
     * Store offer coupon (Merchant) — same payload as admin (titles, price, discount, image, usage_limit, dates).
     */
    public function storeOfferCoupon(Request $request, $offerId): JsonResponse
    {
        $this->assertMerchantOwner($request);
        $offer = Offer::where('id', $offerId)
            ->where('merchant_id', $this->resolveMerchant($request)->id)
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
            $validator->addRules(['image' => ImageUploadRules::fileMax($maxKb)]);
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
        $query = \App\Models\Coupon::where('merchant_id', $this->resolveMerchant($request)->id)
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
        $query = \App\Models\Coupon::where('merchant_id', $this->resolveMerchant($request)->id)
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
        $query = \App\Models\Coupon::where('merchant_id', $this->resolveMerchant($request)->id)
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
        $query = \App\Models\Coupon::where('merchant_id', $this->resolveMerchant($request)->id)
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
    public function deactivateCoupon(Request $request, $id): JsonResponse
    {
        $this->assertMerchantOwner($request);
        $coupon = \App\Models\Coupon::where('merchant_id', $this->resolveMerchant($request)->id)
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