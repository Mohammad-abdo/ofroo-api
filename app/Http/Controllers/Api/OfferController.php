<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfferStoreRequest;
use App\Http\Resources\OfferResource;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Review;
use App\Repositories\OfferRepository;
use App\Services\NotificationService;
use App\Services\OfferService;
use App\Support\ApiMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
     * Search offers (mobile: GET /api/mobile/search?q=... or ?search=...). Requires auth.
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q') ?? $request->input('search');
        if (trim((string) $q) === '') {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
            ]);
        }
        $filters = array_merge($request->all(), ['search' => $q]);
        if ($request->is('api/mobile/*')) {
            $filters['mobile_public'] = true;
        } else {
            $filters['active'] = true;
        }
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
     * Unified mobile search across offers, coupons and categories.
     *
     * Route: GET /api/mobile/search?q=...
     *
     * Returns paginated results with a normalised item shape:
     *   { id, title, image, type }  where type ∈ offer|coupon|category.
     *
     * Arabic (and any UTF-8) input works because the SQL LIKE operand is kept
     * exactly as provided — no strtolower / transliteration which would break
     * Arabic diacritics. We search across both *_ar and *_en columns when they
     * exist in the schema.
     */
    public function searchMobile(Request $request): JsonResponse
    {
        $q = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
        $perPage = max(1, min(50, (int) $request->get('per_page', 15)));
        $page = max(1, (int) $request->get('page', 1));

        if ($q === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => $page,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'q' => '',
                ],
            ]);
        }

        $like = '%' . $q . '%';

        // Offers (respecting mobile visibility rules)
        $offerCols = array_values(array_filter(
            ['title', 'title_ar', 'title_en', 'description', 'description_ar', 'description_en'],
            fn ($c) => Schema::hasColumn('offers', $c)
        ));
        $offers = Offer::query()
            ->mobilePubliclyAvailable()
            ->where(function ($q2) use ($offerCols, $like) {
                foreach ($offerCols as $i => $col) {
                    $i === 0 ? $q2->where($col, 'like', $like) : $q2->orWhere($col, 'like', $like);
                }
            })
            ->limit(200)
            ->get();

        $offerItems = $offers->map(function (Offer $offer) {
            $images = ApiMediaUrl::absoluteList($offer->offer_images ?? []);

            return [
                'id' => (int) $offer->id,
                'title' => (string) ($offer->title ?? $offer->title_en ?? ''),
                'image' => $images[0] ?? '',
                'type' => 'offer',
            ];
        });

        // Coupons attached to active offers
        $couponCols = array_values(array_filter(
            ['title', 'title_ar', 'title_en', 'description', 'description_ar', 'description_en'],
            fn ($c) => Schema::hasColumn('coupons', $c)
        ));
        $coupons = Coupon::query()
            ->where('status', 'active')
            ->whereHas('offer', fn ($q2) => $q2->mobilePubliclyAvailable())
            ->where(function ($q2) use ($couponCols, $like) {
                foreach ($couponCols as $i => $col) {
                    $i === 0 ? $q2->where($col, 'like', $like) : $q2->orWhere($col, 'like', $like);
                }
            })
            ->limit(200)
            ->get();

        $couponItems = $coupons->map(function (Coupon $coupon) {
            return [
                'id' => (int) $coupon->id,
                'title' => (string) ($coupon->title ?? $coupon->title_ar ?? $coupon->title_en ?? ''),
                'image' => ApiMediaUrl::publicAbsolute(is_string($coupon->image) ? $coupon->image : ''),
                'type' => 'coupon',
            ];
        });

        // Categories
        $categoryCols = array_values(array_filter(
            ['name_ar', 'name_en'],
            fn ($c) => Schema::hasColumn('categories', $c)
        ));
        $categories = Category::query()
            ->when(Schema::hasColumn('categories', 'is_active'), fn ($q2) => $q2->where('is_active', true))
            ->where(function ($q2) use ($categoryCols, $like) {
                foreach ($categoryCols as $i => $col) {
                    $i === 0 ? $q2->where($col, 'like', $like) : $q2->orWhere($col, 'like', $like);
                }
            })
            ->limit(200)
            ->get();

        $categoryItems = $categories->map(function (Category $category) {
            return [
                'id' => (int) $category->id,
                'title' => (string) ($category->name_ar ?? $category->name_en ?? ''),
                'image' => ApiMediaUrl::publicAbsolute(
                    is_string($category->image_url ?? null) ? $category->image_url : ''
                ),
                'type' => 'category',
            ];
        });

        $merged = new Collection();
        $merged = $merged->concat($offerItems)->concat($couponItems)->concat($categoryItems)->values();

        $total = $merged->count();
        $sliced = $merged->slice(($page - 1) * $perPage, $perPage)->values()->all();
        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        return response()->json([
            'data' => $sliced,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'q' => $q,
                'counts' => [
                    'offers' => $offerItems->count(),
                    'coupons' => $couponItems->count(),
                    'categories' => $categoryItems->count(),
                ],
            ],
        ]);
    }

    /**
     * Display a listing of offers with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        if ($request->is('api/mobile/*')) {
            $filters['mobile_public'] = true;
        }
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
        if (strtolower((string) ($offer->status ?? '')) === 'pending_approval') {
            $user = $request->user();
            $allowed = false;
            if ($user) {
                if ($user->canAccessAdminPanel()) {
                    $allowed = true;
                } else {
                    $m = $user->merchantForPortal();
                    if ($m && (int) $m->id === (int) $offer->merchant_id) {
                        $allowed = true;
                    }
                }
            }
            if (! $allowed) {
                abort(404);
            }
        }

        if ($request->is('api/mobile/*')) {
            if (! Offer::query()->whereKey($offer->id)->mobilePubliclyAvailable()->exists()) {
                abort(404);
            }
        }

        $offer->load(['merchant', 'category', 'mall', 'branches', 'coupons']);
        $data = (new OfferResource($offer))->toArray($request);
        // تفاصيل التاجر الكاملة + شروط الاستخدام للعرض
        if ($offer->merchant) {
            $data['merchant'] = (new \App\Http\Resources\MerchantResource($offer->merchant, true))->toArray($request);
        }
        $data['terms_conditions_ar'] = $offer->terms_conditions_ar ?? '';
        $data['terms_conditions_en'] = $offer->terms_conditions_en ?? '';

        // Public offer reviews + summary (avg + count)
        $data = array_merge($data, $this->publicOfferReviewsForApi((int) $offer->id));

        return response()->json(['data' => $data]);
    }

    /**
     * Public ratings for an offer (visible_to_public + offer_id). Included on GET …/offers/{id}.
     *
     * @return array{review_summary: array{average_rating: float, count: int}, reviews: list<array<string, mixed>>}
     */
    private function publicOfferReviewsForApi(int $offerId): array
    {
        if (! Schema::hasColumn('reviews', 'offer_id')) {
            return [
                'review_summary' => ['average_rating' => 0.0, 'count' => 0],
                'reviews' => [],
            ];
        }

        $base = Review::query()
            ->where('offer_id', $offerId)
            ->where('visible_to_public', true);

        $count = (clone $base)->count();
        $avg = $count > 0 ? round((float) (clone $base)->avg('rating'), 2) : 0.0;

        $rows = (clone $base)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $reviews = $rows->map(function (Review $r) {
            return [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'notes' => $r->notes,
                'notes_ar' => $r->notes_ar,
                'notes_en' => $r->notes_en,
                'created_at' => $r->created_at?->toIso8601String(),
                'user' => [
                    'id' => $r->user_id,
                    'name' => (string) ($r->user?->name ?? ''),
                ],
            ];
        })->values()->all();

        return [
            'review_summary' => [
                'average_rating' => $avg,
                'count' => $count,
            ],
            'reviews' => $reviews,
        ];
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

        if ($request->status === 'active' && strtolower((string) ($offer->status ?? '')) === 'pending_approval') {
            $u = $request->user();
            if (! $u || ! $u->canAccessAdminPanel()) {
                return response()->json([
                    'message' => 'This offer is awaiting admin approval and cannot be activated yet.',
                    'message_ar' => 'هذا العرض بانتظار موافقة الإدارة ولا يمكن تفعيله بعد.',
                ], 422);
            }
        }

        $previousStatus = strtolower((string) ($offer->status ?? ''));
        $offer->update(['status' => $request->status]);

        if ($request->status === 'active'
            && in_array($previousStatus, ['pending_approval', 'pending', 'draft'], true)) {
            try {
                app(NotificationService::class)->broadcastNewOfferToCustomerUsers(
                    $offer->fresh(['merchant'])
                );
            } catch (\Throwable $e) {
                Log::warning('toggleStatus: new-offer broadcast failed', [
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Offer status updated successfully',
            'status' => $offer->status,
        ]);
    }
}
