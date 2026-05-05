<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Mall;
use App\Models\Merchant;
use App\Support\ApiMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API: كيان المولات (جدول malls) — قائمة مولات + تجار داخل مول.
 *
 * ربط التاجر بالمول: merchants.mall_id أو فرع branches.mall_id — فئة التاجر (مثل «مولات») تصنيف نشاط وليست جدول malls.
 */
class MallController extends Controller
{
    /**
     * Single active mall for mobile / public detail screen.
     * GET /api/mobile/malls/details/{id}  و  GET /api/malls/details/{id}
     *
     * Query (اختياري):
     * - category_id: فلترة التجار بـ merchants.category_id + العروض بـ offers.category_id (نفس المعرف).
     * - merchant_id: فلترة التجار (والعروض داخلهم) بتاجر معيّن داخل هذا المول.
     * - merchant_category_id: فلترة التجار فقط (قديم للتوافق الخلفي).
     * - offer_category_id: فلترة العروض فقط (حسب offers.category_id).
     */
    public function mobileMallDetails(Request $request, string $id): JsonResponse
    {
        if (! ctype_digit($id)) {
            abort(404);
        }

        $mall = Mall::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        $language = $request->get('language', 'ar');

        $data = $this->formatMallForMobileApi($mall, $language);
        $data['merchants'] = $this->merchantsWithMallOffersForMobile($mall, $language, $request);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Decode optional category filters for mall details (merchants + nested offers).
     *
     * @return array{merchant_id: ?int, merchant_category_id: ?int, offer_category_id: ?int}
     */
    protected function mallDetailsCategoryFilterIds(Request $request): array
    {
        $syncCategoryId = $request->filled('category_id') && ctype_digit((string) $request->query('category_id'));

        $merchantId = null;
        if ($request->filled('merchant_id') && ctype_digit((string) $request->query('merchant_id'))) {
            $merchantId = (int) $request->query('merchant_id');
        }

        $merchantCategoryId = null;
        if ($request->filled('merchant_category_id') && ctype_digit((string) $request->query('merchant_category_id'))) {
            $merchantCategoryId = (int) $request->query('merchant_category_id');
        } elseif ($syncCategoryId) {
            $merchantCategoryId = (int) $request->query('category_id');
        }

        $offerCategoryId = null;
        if ($request->filled('offer_category_id') && ctype_digit((string) $request->query('offer_category_id'))) {
            $offerCategoryId = (int) $request->query('offer_category_id');
        } elseif ($syncCategoryId) {
            $offerCategoryId = (int) $request->query('category_id');
        }

        return [
            'merchant_id' => $merchantId,
            'merchant_category_id' => $merchantCategoryId,
            'offer_category_id' => $offerCategoryId,
        ];
    }

    /**
     * Merchants tied to this mall (same rules as GET …/malls/{id}/merchants) with each merchant’s
     * mobile-public offers for this screen: tagged for this mall (offer.mall_id) or not pinned to a mall (null),
     * since the merchant is already scoped to this mall.
     *
     * @return list<array<string, mixed>>
     */
    protected function merchantsWithMallOffersForMobile(Mall $mall, string $language, Request $request): array
    {
        $mallId = (int) $mall->id;
        $filters = $this->mallDetailsCategoryFilterIds($request);
        $merchantId = $filters['merchant_id'];
        $merchantCategoryId = $filters['merchant_category_id'];
        $offerCategoryId = $filters['offer_category_id'];

        $merchants = Merchant::query()
            ->select([
                'id',
                'company_name',
                'company_name_ar',
                'company_name_en',
                'logo_url',
                'category_id',
                'mall_id',
            ])
            ->with([
                'category:id,name_ar,name_en',
                'offers' => function ($q) use ($mallId, $offerCategoryId) {
                    $q->where(function ($w) use ($mallId) {
                        $w->where('mall_id', $mallId)->orWhereNull('mall_id');
                    })
                        ->when($offerCategoryId !== null, fn ($q2) => $q2->where('category_id', $offerCategoryId))
                        ->mobilePubliclyAvailable()
                        ->orderBy('id')
                        ->with(['merchant', 'category', 'mall', 'branches', 'coupons']);
                },
            ])
            ->where('approved', true)
            ->where(function ($q) {
                $q->whereNull('is_blocked')->orWhere('is_blocked', false);
            })
            ->associatedWithMall($mallId)
            ->when($merchantId !== null, fn ($q) => $q->whereKey($merchantId))
            ->when($merchantId === null && $merchantCategoryId !== null, fn ($q) => $q->where('category_id', $merchantCategoryId))
            ->orderBy('company_name_ar')
            ->orderBy('id')
            ->get();

        return $merchants->map(function (Merchant $merchant) use ($language, $request) {
            $name = $language === 'ar'
                ? ($merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name ?? '')
                : ($merchant->company_name_en ?? $merchant->company_name_ar ?? $merchant->company_name ?? '');

            $cat = $merchant->category;
            $categoryName = $cat
                ? ($language === 'ar' ? ($cat->name_ar ?? $cat->name_en) : ($cat->name_en ?? $cat->name_ar))
                : null;

            $offers = $merchant->offers
                ->map(fn ($offer) => (new OfferResource($offer))->toArray($request))
                ->values()
                ->all();

            return [
                'id' => $merchant->id,
                'name' => $name,
                'logo_url' => ApiMediaUrl::publicAbsolute(is_string($merchant->logo_url) ? $merchant->logo_url : ''),
                'category_id' => $merchant->category_id,
                'category_name' => $categoryName,
                'offers' => $offers,
            ];
        })->values()->all();
    }

    /**
     * قائمة المولات النشطة (لشاشة اختيار المول).
     * GET /api/mobile/malls  و  GET /api/malls
     */
    public function index(Request $request): JsonResponse
    {
        $language = $request->get('language', 'ar');

        $malls = Mall::query()
            ->where('is_active', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->paginate(max(1, min(100, (int) $request->get('per_page', 30))));

        $data = $malls->getCollection()->map(fn (Mall $mall) => $this->formatMallForMobileApi($mall, $language))
            ->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $malls->currentPage(),
                'last_page' => $malls->lastPage(),
                'per_page' => $malls->perPage(),
                'total' => $malls->total(),
            ],
        ]);
    }

    /**
     * تجار مرتبطون بمول معيّن، مع فلتر اختياري بالفئة (تصنيف النشاط التجاري للتاجر).
     * GET .../malls/{mallId}/merchants?category_id=&language=&per_page=
     *
     * بعد اختيار تاجر: استخدم GET /merchants/{id} و GET /merchants/{id}/offers (موجودان).
     */
    public function merchants(Request $request, string $mallId): JsonResponse
    {
        if (! ctype_digit($mallId)) {
            abort(404);
        }

        Mall::query()
            ->where('id', $mallId)
            ->where('is_active', true)
            ->firstOrFail();

        $language = $request->get('language', 'ar');
        $categoryId = $request->get('category_id');

        $query = Merchant::query()
            ->select([
                'id',
                'company_name',
                'company_name_ar',
                'company_name_en',
                'logo_url',
                'category_id',
                'mall_id',
            ])
            ->with(['category:id,name_ar,name_en'])
            ->where('approved', true)
            ->where(function ($q) {
                $q->whereNull('is_blocked')->orWhere('is_blocked', false);
            })
            ->associatedWithMall($mallId);

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));
        $merchants = $query
            ->orderBy('company_name_ar')
            ->orderBy('id')
            ->paginate($perPage);

        $data = $merchants->getCollection()->map(function (Merchant $merchant) use ($language) {
            $name = $language === 'ar'
                ? ($merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name ?? '')
                : ($merchant->company_name_en ?? $merchant->company_name_ar ?? $merchant->company_name ?? '');

            $cat = $merchant->category;
            $categoryName = $cat
                ? ($language === 'ar' ? ($cat->name_ar ?? $cat->name_en) : ($cat->name_en ?? $cat->name_ar))
                : null;

            return [
                'id' => $merchant->id,
                'name' => $name,
                'logo_url' => ApiMediaUrl::publicAbsolute(is_string($merchant->logo_url) ? $merchant->logo_url : ''),
                'category_id' => $merchant->category_id,
                'category_name' => $categoryName,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $merchants->currentPage(),
                'last_page' => $merchants->lastPage(),
                'per_page' => $merchants->perPage(),
                'total' => $merchants->total(),
                'mall_id' => (int) $mallId,
            ],
        ]);
    }

    /**
     * All merchants tied to a mall (no pagination).
     * GET /api/mobile/malls/{mallId}/merchants/all  و  GET /api/malls/{mallId}/merchants/all
     */
    public function merchantsAll(Request $request, string $mallId): JsonResponse
    {
        if (! ctype_digit($mallId)) {
            abort(404);
        }

        Mall::query()
            ->where('id', $mallId)
            ->where('is_active', true)
            ->firstOrFail();

        $language = $request->get('language', 'ar');

        $merchants = Merchant::query()
            ->select([
                'id',
                'company_name',
                'company_name_ar',
                'company_name_en',
                'logo_url',
                'category_id',
                'mall_id',
            ])
            ->with(['category:id,name_ar,name_en'])
            ->where('approved', true)
            ->where(function ($q) {
                $q->whereNull('is_blocked')->orWhere('is_blocked', false);
            })
            ->associatedWithMall($mallId)
            ->orderBy('company_name_ar')
            ->orderBy('id')
            ->get();

        $data = $merchants->map(function (Merchant $merchant) use ($language) {
            $name = $language === 'ar'
                ? ($merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name ?? '')
                : ($merchant->company_name_en ?? $merchant->company_name_ar ?? $merchant->company_name ?? '');

            $cat = $merchant->category;
            $categoryName = $cat
                ? ($language === 'ar' ? ($cat->name_ar ?? $cat->name_en) : ($cat->name_en ?? $cat->name_ar))
                : null;

            return [
                'id' => $merchant->id,
                'name' => $name,
                'logo_url' => ApiMediaUrl::publicAbsolute(is_string($merchant->logo_url) ? $merchant->logo_url : ''),
                'category_id' => $merchant->category_id,
                'category_name' => $categoryName,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => count($data),
                'mall_id' => (int) $mallId,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatMallForMobileApi(Mall $mall, string $language): array
    {
        $isAr = $language === 'ar';

        $pick = static function (?string $ar, ?string $en, ?string $legacy = null) use ($isAr): string {
            $ar = $ar !== null && $ar !== '' ? trim($ar) : '';
            $en = $en !== null && $en !== '' ? trim($en) : '';
            $legacy = $legacy !== null && $legacy !== '' ? trim($legacy) : '';

            if ($isAr) {
                return $ar !== '' ? $ar : ($en !== '' ? $en : $legacy);
            }

            return $en !== '' ? $en : ($ar !== '' ? $ar : $legacy);
        };

        $name = $pick($mall->name_ar, $mall->name_en, $mall->name);
        $description = $pick($mall->description_ar, $mall->description_en, $mall->description);
        $address = $pick($mall->address_ar, $mall->address_en, $mall->address);

        $openingHours = $mall->opening_hours;
        if (! is_array($openingHours)) {
            $openingHours = null;
        }

        return [
            'id' => $mall->id,
            'name_ar' => $mall->name_ar ?? $mall->name ?? '',
            'name_en' => $mall->name_en ?? $mall->name ?? '',
            'description_ar' => $mall->description_ar ?? $mall->description ?? '',
            'description_en' => $mall->description_en ?? $mall->description ?? '',
            'address_ar' => $mall->address_ar ?? $mall->address ?? '',
            'address_en' => $mall->address_en ?? $mall->address ?? '',
            'city' => $mall->city ?? '',
            'country' => $mall->country ?? '',
            'city_id' => $mall->city_id,
            'phone' => $mall->phone ?? '',
            'email' => $mall->email ?? '',
            'website' => $mall->website ?? '',
            'image_url' => ApiMediaUrl::publicAbsolute(is_string($mall->image_url) ? $mall->image_url : ''),
            'images' => ApiMediaUrl::absoluteList($mall->images ?? []),
            'opening_hours' => $openingHours,
            'order_index' => (int) ($mall->order_index ?? 0),
            'is_active' => (bool) $mall->is_active,
        ];
    }
}
