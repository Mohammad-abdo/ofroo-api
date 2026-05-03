<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\ActivationReport;
use App\Models\AppCouponSetting;
use App\Models\Coupon;
use App\Models\MallCoupon;
use App\Models\Offer;
use App\Support\ImageUploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
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
        $isOfferBased = $request->filled('offer_id') && ! $request->filled('category_id');

        if ($isOfferBased) {
            $rules = [
                'offer_id' => 'required|exists:offers,id',
                'title' => 'nullable|string|max:255',
                'title_ar' => 'nullable|string|max:255',
                'title_en' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'description_ar' => 'nullable|string',
                'description_en' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:percent,amount,percentage,fixed',
                'barcode' => 'nullable|string|max:64',
                'coupon_code' => 'nullable|string|unique:coupons,coupon_code',
                'usage_limit' => 'nullable|integer|min:0',
                'status' => 'nullable|in:active,inactive,used,expired,pending',
                'starts_at' => 'nullable|date',
                'expires_at' => 'nullable|date',
                'image' => 'nullable',
            ];
            if ($request->hasFile('image')) {
                $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
                $rules['image'] = ImageUploadRules::fileMax($maxKb);
            }
        } else {
            $rules = [
                'category_id' => 'required|exists:categories,id',
                'mall_id' => 'required|exists:malls,id',
                'coupon_code' => 'nullable|string|unique:coupons,coupon_code',
                'usage_limit' => 'required|integer|min:1',
                'discount_type' => 'required|in:percent,amount,percentage,fixed',
                'discount_percent' => 'nullable|required_if:discount_type,percent|numeric|min:0|max:100',
                'discount_amount' => 'nullable|required_if:discount_type,amount|numeric|min:0',
                'status' => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired,active,inactive',
                'expires_at' => 'nullable|date',
                'terms_conditions' => 'nullable|string',
                'is_refundable' => 'nullable|boolean',
                'offer_id' => 'nullable|exists:offers,id',
                'barcode_value' => 'nullable|string',
            ];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($isOfferBased) {
            $offer = Offer::findOrFail($request->offer_id);
            try {
                AppCouponSetting::assertOfferCanAddCoupon($offer);
            } catch (ValidationException $e) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            $dt = $request->input('discount_type', 'percent');
            $mappedDt = in_array($dt, ['fixed', 'amount'], true) ? 'amount' : 'percent';
            $barcodeVal = $request->input('barcode') ?: ('CPN-'.strtoupper(uniqid()));
            $imagePath = null;
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $imagePath = asset('storage/'.$request->file('image')->store('coupons', 'public'));
            } elseif ($request->filled('image') && is_string($request->image)) {
                $imagePath = $request->image;
            }

            $coupon = Coupon::create([
                'offer_id' => $request->offer_id,
                'coupon_setting_id' => AppCouponSetting::current()->id,
                'title' => $request->input('title', $request->input('title_ar', '')),
                'title_ar' => $request->input('title_ar'),
                'title_en' => $request->input('title_en'),
                'description' => $request->input('description', $request->input('description_ar', '')),
                'description_ar' => $request->input('description_ar'),
                'description_en' => $request->input('description_en'),
                'price' => (float) $request->price,
                'discount' => (float) ($request->discount ?? 0),
                'discount_type' => $mappedDt,
                'barcode' => $barcodeVal,
                'coupon_code' => $request->input('coupon_code', $barcodeVal),
                'usage_limit' => (int) ($request->usage_limit ?? 0),
                'times_used' => 0,
                'status' => $request->input('status', 'active'),
                'starts_at' => $request->starts_at ? date('Y-m-d H:i:s', strtotime($request->starts_at)) : null,
                'expires_at' => $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null,
                'image' => $imagePath,
            ]);
        } else {
            $couponCode = $request->coupon_code ?? 'CPN-'.strtoupper(uniqid());
            $coupon = Coupon::create([
                'coupon_setting_id' => AppCouponSetting::current()->id,
                'category_id' => $request->category_id,
                'mall_id' => $request->mall_id,
                'offer_id' => $request->offer_id,
                'coupon_code' => $couponCode,
                'barcode_value' => $request->barcode_value ?? $couponCode,
                'usage_limit' => $request->usage_limit,
                'times_used' => 0,
                'status' => $request->input('status', 'pending'),
                'expires_at' => $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null,
                'terms_conditions' => $request->terms_conditions,
                'is_refundable' => $request->boolean('is_refundable', false),
                'discount_type' => $request->input('discount_type', 'percent'),
                'discount_percent' => $request->discount_type === 'percent' ? $request->discount_percent : null,
                'discount_amount' => $request->discount_type === 'amount' ? $request->discount_amount : null,
                'created_by' => auth()->id(),
                'created_by_type' => 'admin',
            ]);
        }

        if ($request->filled('offer_id')) {
            $offer = Offer::find($request->offer_id);
            if ($offer) {
                $offer->update(['coupon_id' => $coupon->id]);
            }
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
            'usage_limit' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:percent,amount,percentage,fixed',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,reserved,paid,activated,used,cancelled,expired,active,inactive',
            'expires_at' => 'nullable|date',
            'terms_conditions' => 'nullable|string',
            'is_refundable' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|unique:coupons,coupon_code,'.$id,
            'barcode_value' => 'nullable|string',
            'offer_id' => 'nullable|exists:offers,id',
            // Offer-based coupon fields (same as merchant)
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
            $updateData['status'] = $request->status;
        }

        if ($request->has('expires_at')) {
            $updateData['expires_at'] = $request->expires_at ? date('Y-m-d H:i:s', strtotime($request->expires_at)) : null;
        }

        if ($request->has('starts_at')) {
            $updateData['starts_at'] = $request->starts_at ? date('Y-m-d H:i:s', strtotime($request->starts_at)) : null;
        }

        // Legacy discount fields
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
     * Get mall coupons (Admin) - منقول من التاجر
     */
    public function getMallCoupons(Request $request): JsonResponse
    {
        $query = MallCoupon::with(['category', 'mall']);

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
        $query = ActivationReport::with(['coupon', 'user', 'merchant', 'order']);

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('coupon_id')) {
            $query->where('coupon_id', $request->coupon_id);
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
