<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfferStoreRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Repositories\OfferRepository;
use App\Services\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfferController extends Controller
{
    protected $offerRepository;
    protected $offerService;

    public function __construct(OfferRepository $offerRepository, OfferService $offerService)
    {
        $this->offerRepository = $offerRepository;
        $this->offerService = $offerService;
    }

    /**
     * Display a listing of offers with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $offers = $this->offerRepository->getOffers($filters);

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
     * Store a newly created offer in storage.
     */
    public function store(OfferStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', Offer::class);

        $data = $this->prepareOfferData($request);
        $offer = $this->offerService->createOffer($data);

        return response()->json([
            'message' => 'Offer created successfully',
            'data' => new OfferResource($offer),
        ], 201);
    }

    /**
     * Prepare offer data: upload offer images, attach coupon image files.
     */
    protected function prepareOfferData(OfferStoreRequest $request): array
    {
        $data = $request->validated();
        $imageUrls = [];
        $offerImagesInput = $request->input('offer_images', []) ?: [];
        $offerImagesFiles = $request->file('offer_images');
        if (!is_array($offerImagesFiles)) {
            $offerImagesFiles = $offerImagesFiles ? [$offerImagesFiles] : [];
        }
        // Preserve order: existing URLs then new uploads (frontend sends URLs first, then files)
        foreach ($offerImagesInput as $img) {
            if (is_string($img) && (str_starts_with($img, 'http') || str_starts_with($img, '/'))) {
                $imageUrls[] = $img;
            }
        }
        foreach ($offerImagesFiles as $image) {
            if ($image && $image->isValid()) {
                $path = $image->store('offers', 'public');
                $imageUrls[] = asset('storage/' . $path);
            }
        }
        if (!empty($imageUrls)) {
            $data['offer_images'] = $imageUrls;
        }

        $couponImages = $request->file('coupon_images');
        if ($couponImages !== null) {
            $data['coupon_image_files'] = is_array($couponImages) ? $couponImages : [$couponImages];
        }

        return $data;
    }

    /**
     * Display the specified offer (with full merchant details and terms).
     */
    public function show(Request $request, Offer $offer): JsonResponse
    {
        $offer->load(['merchant', 'category', 'mall', 'branches', 'coupons']);
        $data = (new OfferResource($offer))->toArray($request);
        // تفاصيل التاجر الكاملة + شروط الاستخدام للعرض
        if ($offer->merchant) {
            $data['merchant'] = (new \App\Http\Resources\MerchantResource($offer->merchant, true))->toArray($request);
        }
        $data['terms_conditions_ar'] = $offer->terms_conditions_ar ?? '';
        $data['terms_conditions_en'] = $offer->terms_conditions_en ?? '';
        return response()->json(['data' => $data]);
    }

    /**
     * Update the specified offer in storage.
     */
    public function update(OfferStoreRequest $request, Offer $offer): JsonResponse
    {
        Gate::authorize('update', $offer);

        $data = $this->prepareOfferData($request);
        $offer = $this->offerService->updateOffer($offer, $data);

        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => new OfferResource($offer),
        ]);
    }

    /**
     * Remove the specified offer from storage.
     */
    public function destroy(Offer $offer): JsonResponse
    {
        Gate::authorize('delete', $offer);

        $offer->delete();

        return response()->json([
            'message' => 'Offer deleted successfully',
        ]);
    }

    /**
     * Toggle favorite status for the authenticated user.
     */
    /**
     * Toggle favorite (requires auth + token). Only authenticated user can add/remove favorites.
     */
    public function toggleFavorite(Request $request, Offer $offer): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated. You must log in to add favorites.',
                'message_ar' => 'يجب تسجيل الدخول لإضافة العروض إلى المفضلة. الرجاء تسجيل الدخول أو إنشاء حساب.',
                'message_en' => 'You must be logged in to add favorites.',
            ], 401);
        }

        $isFavorite = $this->offerService->toggleFavorite($offer, $user->id);

        return response()->json([
            'message' => $isFavorite ? 'Offer added to favorites' : 'Offer removed from favorites',
            'is_favorite' => $isFavorite,
        ]);
    }

    /**
     * Toggle offer status (Admin only or Merchant for his own).
     */
    public function toggleStatus(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('toggleStatus', $offer);

        $request->validate([
            'status' => 'required|in:active,disabled',
        ]);

        $offer->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Offer status updated successfully',
            'status' => $offer->status,
        ]);
    }
}
