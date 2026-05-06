<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Services\NotificationService;
use App\Services\OfferService;
use App\Support\ImageUploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OfferController extends Controller
{
    public function __construct(
        protected ?OfferService $offerService = null
    ) {
        $this->offerService = $this->offerService ?? app(OfferService::class);
    }

    /**
     * Map bilingual fields into DB columns: `title` = Arabic (primary), `title_en` = English.
     */
    protected function mergeOfferBilingualTitles(Request $request): void
    {
        if (! $request->hasAny(['title_ar', 'title_en', 'title'])) {
            return;
        }
        $titleAr = trim((string) $request->input('title_ar', ''));
        $titleEn = trim((string) $request->input('title_en', ''));
        $legacy = trim((string) $request->input('title', ''));
        if ($titleAr === '' && $legacy !== '') {
            $titleAr = $legacy;
        }
        $primary = $titleAr !== '' ? $titleAr : $titleEn;
        $request->merge([
            'title' => $primary,
            'title_en' => $titleEn !== '' ? $titleEn : null,
        ]);
    }

    /**
     * Map bilingual fields: `description` = Arabic (primary), `description_en` = English.
     */
    protected function mergeOfferBilingualDescriptions(Request $request): void
    {
        if (! $request->hasAny(['description_ar', 'description_en', 'description'])) {
            return;
        }
        $descAr = trim((string) $request->input('description_ar', ''));
        $descEn = trim((string) $request->input('description_en', ''));
        $legacy = trim((string) $request->input('description', ''));
        if ($descAr === '' && $legacy !== '') {
            $descAr = $legacy;
        }
        $primary = $descAr !== '' ? $descAr : $descEn;
        $request->merge([
            'description' => $primary !== '' ? $primary : null,
            'description_en' => $descEn !== '' ? $descEn : null,
        ]);
    }

    /**
     * List all offers (for admin approval)
     */
    public function offers(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 500);
        $forSelect = $request->boolean('for_select', false);

        // When a screen only needs a dropdown list (e.g. Ads form),
        // avoid eager-loading heavy relations; some merchants have hundreds of offers
        // which can cause timeouts and the UI falls back to "no offers".
        $base = Offer::query()
            ->when($request->has('status') && $request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->has('merchant_id') && $request->merchant_id, function ($query) use ($request) {
                $query->where('merchant_id', $request->merchant_id);
            })
            ->when($request->has('category_id') && $request->category_id, function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->when($request->filled('mall_id'), function ($query) use ($request) {
                $query->where('mall_id', $request->mall_id);
            })
            ->when($request->has('search') && $request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('title_en', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('description_en', 'like', "%{$search}%")
                        ->orWhereHas('merchant', function ($merchantQuery) use ($search) {
                            $merchantQuery->where('company_name', 'like', "%{$search}%")
                                ->orWhere('company_name_ar', 'like', "%{$search}%")
                                ->orWhere('company_name_en', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc');

        if ($forSelect) {
            $offers = $base
                ->select(['id', 'merchant_id', 'title', 'title_en', 'status', 'start_date', 'end_date'])
                ->paginate($perPage);

            return response()->json([
                'data' => $offers->items(),
                'meta' => [
                    'current_page' => $offers->currentPage(),
                    'last_page' => $offers->lastPage(),
                    'per_page' => $offers->perPage(),
                    'total' => $offers->total(),
                ],
            ]);
        }

        $offers = $base
            ->with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->paginate($perPage);

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
     * Get single offer (Admin)
     */
    public function getOffer(string $id): JsonResponse
    {
        $offer = Offer::with(['merchant', 'category', 'mall', 'branches', 'coupons'])->findOrFail($id);

        return response()->json([
            'data' => new OfferResource($offer),
        ]);
    }

    /**
     * Create offer (Admin)
     */
    public function createOffer(Request $request): JsonResponse
    {
        $this->mergeOfferBilingualTitles($request);
        $this->mergeOfferBilingualDescriptions($request);

        $rules = [
            'merchant_id' => 'required|exists:merchants,id',
            'category_id' => 'required|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:active,expired,disabled,pending',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
        ];

        $contentType = $request->header('Content-Type', '');
        $isMultipart = ! empty($contentType) && str_contains(strtolower($contentType), 'multipart/form-data');
        $validationData = $request->all();
        if ($isMultipart && isset($validationData['offer_images'])) {
            unset($validationData['offer_images']);
        }
        if ($isMultipart) {
            $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
            $rules['offer_images'] = 'nullable|array';
            $rules['offer_images.*'] = ImageUploadRules::nullableFileMax($maxKb);
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
                    $imageName = time().'_'.uniqid().'.'.$image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('offers', $imageName, 'public');
                    $imageUrls[] = asset('storage/'.$imagePath);
                }
            }
        } elseif ($request->has('offer_images') && is_array($request->offer_images)) {
            foreach ($request->offer_images as $img) {
                if (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                    $imageUrls[] = $img;
                }
            }
        }

        $offer = Offer::create([
            'merchant_id' => $request->merchant_id,
            'category_id' => $request->category_id,
            'mall_id' => $request->mall_id,
            'title' => $request->title,
            'title_en' => $request->input('title_en'),
            'description' => $request->description,
            'description_en' => $request->input('description_en'),
            'price' => $request->input('price') !== null && $request->input('price') !== '' ? (float) $request->price : 0,
            'discount' => $request->input('discount') !== null && $request->input('discount') !== '' ? (float) $request->discount : 0,
            'offer_images' => $imageUrls,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'pending',
        ]);

        if ($request->has('branches') && is_array($request->branches)) {
            $offer->branches()->sync($request->branches);
        }

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
            if (! is_array($couponData)) {
                continue;
            }
            $imageFile = $couponImageFiles[$index] ?? null;
            try {
                $this->offerService->createCouponForOffer($offer, $couponData, $imageFile);
            } catch (\Throwable $e) {
                Log::warning('Admin createOffer: failed to create coupon for offer '.$offer->id.': '.$e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Offer created successfully',
            'data' => new OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ], 201);
    }

    /**
     * Update offer (Admin)
     */
    public function updateOffer(Request $request, string $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);

        $input = $request->all();
        foreach (['merchant_id', 'category_id', 'mall_id'] as $key) {
            if (isset($input[$key]) && (is_string($input[$key]) && trim($input[$key]) === '')) {
                $input[$key] = null;
            }
        }
        $request->merge($input);
        $this->mergeOfferBilingualTitles($request);
        $this->mergeOfferBilingualDescriptions($request);

        $rules = [
            'merchant_id' => 'nullable|exists:merchants,id',
            'category_id' => 'nullable|exists:categories,id',
            'mall_id' => 'nullable|exists:malls,id',
            'title' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|in:active,expired,disabled,pending',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
        ];

        $contentType = $request->header('Content-Type', '');
        $isMultipart = ! empty($contentType) && str_contains(strtolower($contentType), 'multipart/form-data');
        $validationData = $request->all();
        if ($isMultipart && isset($validationData['offer_images'])) {
            unset($validationData['offer_images']);
        }
        if ($isMultipart) {
            $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
            $rules['offer_images'] = 'nullable|array';
            $rules['offer_images.*'] = ImageUploadRules::nullableFileMax($maxKb);
        }

        $validator = Validator::make($validationData, $rules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $updateData = $request->only([
            'merchant_id', 'category_id', 'mall_id', 'title', 'title_en', 'description', 'description_en',
            'price', 'discount', 'start_date', 'end_date', 'status',
        ]);

        $imageUrls = [];
        if ($request->hasFile('offer_images')) {
            $files = $request->file('offer_images');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $image) {
                if ($image instanceof UploadedFile && $image->isValid()) {
                    $imageName = time().'_'.uniqid().'.'.$image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('offers', $imageName, 'public');
                    $imageUrls[] = asset('storage/'.$imagePath);
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
        if (! empty($imageUrls)) {
            $updateData['offer_images'] = $imageUrls;
        }

        $offer->update(array_filter($updateData, fn ($v) => $v !== ''));

        if ($request->has('branches')) {
            $offer->branches()->sync($request->branches ?? []);
        }

        if ($request->has('coupons')) {
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

            $offer->coupons()->delete();
            foreach ($couponsList as $index => $couponData) {
                if (! is_array($couponData)) {
                    continue;
                }
                $imageFile = $couponImageFiles[$index] ?? null;
                try {
                    $this->offerService->createCouponForOffer($offer, $couponData, $imageFile);
                } catch (\Throwable $e) {
                    Log::warning('Admin updateOffer: failed to recreate coupon for offer '.$offer->id.': '.$e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => new OfferResource($offer->fresh()->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ]);
    }

    /**
     * Delete offer (Admin)
     */
    public function deleteOffer(string $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);

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
        $offer = Offer::findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,rejected',
        ]);

        $newOfferStatus = $request->status === 'active' ? 'active' : 'disabled';

        DB::transaction(function () use ($offer, $request, $newOfferStatus) {
            $offer->update(['status' => $newOfferStatus]);

            if ($request->status === 'active') {
                $offer->coupons()->where('status', 'pending')->update(['status' => 'active']);
            } else {
                $offer->coupons()->whereIn('status', ['pending', 'active'])->update(['status' => 'expired']);
            }
        });

        $offer->refresh();

        if ($request->status === 'active') {
            try {
                $offer->loadMissing('merchant');
                $processed = app(NotificationService::class)->broadcastNewOfferToCustomerUsers($offer);
                Log::info('approveOffer: new-offer customer notifications', [
                    'offer_id' => $offer->id,
                    'users_notified' => $processed,
                ]);
            } catch (\Throwable $e) {
                Log::warning('approveOffer: new-offer broadcast failed', [
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Offer '.($request->status === 'active' ? 'approved' : 'rejected').' successfully',
            'data' => new OfferResource($offer->load(['merchant', 'category', 'mall', 'branches', 'coupons'])),
        ]);
    }

    /**
     * Create coupon for an offer (Admin) - same logic as merchant offer coupons.
     */
    public function storeOfferCoupon(Request $request, string $offerId): JsonResponse
    {
        $offer = Offer::findOrFail($offerId);

        $validator = Validator::make($request->all(), [
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

            return response()->json([
                'message' => 'Coupon created successfully',
                'data' => new CouponResource($coupon->load('offer')),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }
    }
}
