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
 * ربط التاجر بالمول: merchants.mall_id أو فرع branches.mall_id فقط — لا يُستنتج من فئة العرض/التاجر (مثل name_ar «مولات»).
 */
class MallController extends Controller
{
    /**
     * Single active mall for mobile / public detail screen.
     * GET /api/mobile/malls/details/{id}  و  GET /api/malls/details/{id}
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
     * Merchants tied to this mall (same rules as GET …/malls/{id}/merchants) with each merchant’s
     * offers where offer.mall_id matches this mall only (mobile-public offers).
     *
     * @return list<array<string, mixed>>
     */
    protected function merchantsWithMallOffersForMobile(Mall $mall, string $language, Request $request): array
    {
        $mallId = (int) $mall->id;

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
                'offers' => function ($q) use ($mallId) {
                    $q->where('mall_id', $mallId)
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
