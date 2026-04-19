<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Support\ApiMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API: صفحة تفاصيل التاجر + عروض التاجر (للموبايل بدون مصادقة).
 */
class MerchantProfileController extends Controller
{
    /**
     * عدد العروض المعروضة في صفحة تفاصيل التاجر (عروض التاجر الخاصة).
     */
    protected const OFFERS_PER_DETAIL_PAGE = 10;

    /**
     * عروض التاجر للاستجابة العامة: على الموبايل فقط العروض المتاحة فعلياً؛ على /api بدون mobile نفس سلوك status active السابق.
     */
    private function merchantOffersForRequest(\App\Models\Merchant $merchant, Request $request)
    {
        $q = $merchant->offers();
        if ($request->is('api/mobile/*')) {
            return $q->mobilePubliclyAvailable();
        }

        return $q->where('status', 'active');
    }

    /**
     * قائمة التجار للموبايل: id, name, image فقط.
     * GET /api/mobile/merchants?language=ar
     */
    public function index(Request $request): JsonResponse
    {
        $language = $request->get('language', 'ar');

        $merchants = Merchant::select('id', 'company_name', 'company_name_ar', 'company_name_en', 'logo_url')
            ->where('approved', true)
            ->where(function ($q) {
                $q->whereNull('is_blocked')->orWhere('is_blocked', false);
            })
            ->orderBy('company_name_ar')
            ->orderBy('id')
            ->get();

        $data = $merchants->map(function ($merchant) use ($language) {
            $name = $language === 'ar'
                ? ($merchant->company_name_ar ?? $merchant->company_name_en ?? $merchant->company_name ?? '')
                : ($merchant->company_name_en ?? $merchant->company_name_ar ?? $merchant->company_name ?? '');
            return [
                'id' => $merchant->id,
                'name' => $name,
                'logo_url' => ApiMediaUrl::publicAbsolute(is_string($merchant->logo_url) ? $merchant->logo_url : ''),
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * صفحة تفاصيل التاجر: بيانات التاجر + عروض فقط (بدون تفاصيل تاجر/كوبونات داخل كل عرض).
     * ?only_offers=1 يرجع العروض فقط بدون أي بيانات تاجر.
     * GET /api/mobile/merchants/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $language = $request->get('language');
        $user = $request->user();
        if (!$language && $user) {
            $language = $user->language ?? 'ar';
        }
        $language = $language ?? 'ar';

        $merchant = Merchant::with(['category', 'branches', 'reviews'])
            ->where('id', $id)
            ->where('approved', true)
            ->firstOrFail();

        // إذا طلب العروض فقط: نفس استجابة GET .../merchants/{id}/offers
        if ($request->boolean('only_offers')) {
            $offers = $this->merchantOffersForRequest($merchant, $request)
                ->with(['category:id,name_ar,name_en', 'mall:id,name,name_ar,name_en'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $data = $offers->getCollection()->map(function ($offer) use ($language) {
                return $this->formatOfferMinimal($offer, $language);
            })->values()->all();

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $offers->currentPage(),
                    'last_page' => $offers->lastPage(),
                    'per_page' => $offers->perPage(),
                    'total' => $offers->total(),
                ],
            ]);
        }

        $rating = (float) $merchant->reviews()->where('visible_to_public', true)->avg('rating');
        $reviewsCount = $merchant->reviews()->where('visible_to_public', true)->count();

        $categoryName = '';
        if ($merchant->category) {
            $categoryName = $language === 'ar'
                ? ($merchant->category->name_ar ?? $merchant->category->name_en)
                : ($merchant->category->name_en ?? $merchant->category->name_ar);
        }

        $offers = $this->merchantOffersForRequest($merchant, $request)
            ->with(['category:id,name_ar,name_en', 'mall:id,name,name_ar,name_en'])
            ->orderBy('created_at', 'desc')
            ->limit(self::OFFERS_PER_DETAIL_PAGE)
            ->get();

        $offersData = $offers->map(fn ($offer) => $this->formatOfferMinimal($offer, $language))->values()->all();

        $mainBranch = $merchant->branches->first();
        $address = $merchant->address_ar ?? $merchant->address_en ?? $merchant->address ?? '';
        if ($mainBranch) {
            $address = $address ?: ($mainBranch->address_ar ?? $mainBranch->address_en ?? $mainBranch->address ?? '');
        }

        $data = [
            'id' => $merchant->id,
            'company_name' => $merchant->company_name ?? '',
            'company_name_ar' => $merchant->company_name_ar ?? $merchant->company_name ?? '',
            'company_name_en' => $merchant->company_name_en ?? $merchant->company_name ?? '',
            'logo_url' => ApiMediaUrl::publicAbsolute(is_string($merchant->logo_url) ? $merchant->logo_url : ''),
            'city' => $merchant->city ?? '',
            'country' => $merchant->country ?? '',
            'category_name' => $categoryName,
            'rating' => round($rating, 1),
            'reviews_count' => $reviewsCount,
            'description' => $language === 'ar'
                ? ($merchant->description_ar ?? $merchant->description ?? '')
                : ($merchant->description_en ?? $merchant->description ?? ''),
            'description_ar' => $merchant->description_ar ?? $merchant->description ?? '',
            'description_en' => $merchant->description_en ?? $merchant->description ?? '',
            'phone' => $merchant->phone ?? '',
            'whatsapp_number' => $merchant->whatsapp_number ?? '',
            'whatsapp_link' => $merchant->whatsapp_link ?? '',
            'whatsapp_enabled' => (bool) ($merchant->whatsapp_enabled ?? false),
            'address' => $address,
            'address_ar' => $merchant->address_ar ?? $merchant->address ?? '',
            'address_en' => $merchant->address_en ?? $merchant->address ?? '',
            'branches' => $merchant->branches->map(function ($b) use ($language) {
                $name = $language === 'ar'
                    ? ($b->name_ar ?? $b->name_en ?? $b->name)
                    : ($b->name_en ?? $b->name_ar ?? $b->name);
                return [
                    'id' => $b->id,
                    'name' => $name ?? '',
                    'name_ar' => $b->name_ar ?? '',
                    'name_en' => $b->name_en ?? '',
                    'phone' => $b->phone ?? '',
                    'lat' => $b->lat !== null ? (float) $b->lat : null,
                    'lng' => $b->lng !== null ? (float) $b->lng : null,
                    'address' => $b->address ?? $b->address_ar ?? $b->address_en ?? '',
                    'is_active' => (bool) ($b->is_active ?? true),
                ];
            })->values()->all(),
            'social_links' => [],
            'offers' => $offersData,
            'offers_total' => $this->merchantOffersForRequest($merchant, $request)->count(),
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * شكل واحد للعرض (عروض فقط - بدون merchant/category/mall/branches/coupons).
     */
    private function formatOfferMinimal($offer, string $language): array
    {
        $categoryName = $offer->category
            ? ($language === 'ar' ? ($offer->category->name_ar ?? $offer->category->name_en) : ($offer->category->name_en ?? $offer->category->name_ar))
            : null;
        $mallName = $offer->mall
            ? ($language === 'ar' ? ($offer->mall->name_ar ?? $offer->mall->name) : ($offer->mall->name_en ?? $offer->mall->name))
            : null;
        return [
            'id' => $offer->id,
            'title' => $offer->title ?? '',
            'description' => $offer->description ?? '',
            'price' => (float) $offer->price,
            'discount' => (float) ($offer->discount ?? 0),
            'offer_images' => ApiMediaUrl::absoluteList($offer->offer_images ?? []),
            'start_date' => $offer->start_date?->toIso8601String(),
            'end_date' => $offer->end_date?->toIso8601String(),
            'status' => $offer->status ?? 'active',
            'is_expired' => $offer->isExpired(),
            'is_not_started' => $offer->isNotYetStarted(),
            'effective_status' => $offer->effectiveStatus(),
            'status_label_ar' => $offer->isExpired() ? 'هذا العرض منتهي' : ($offer->isNotYetStarted() ? 'العرض لم يبدأ بعد' : ''),
            'status_label_en' => $offer->isExpired() ? 'This offer has expired' : ($offer->isNotYetStarted() ? 'This offer has not started yet' : ''),
            'category_name' => $categoryName,
            'mall_name' => $mallName,
        ];
    }

    /**
     * كل عروض التاجر فقط (مع pagination) - بدون تفاصيل تاجر/مول/فروع/كوبونات.
     * GET /api/mobile/merchants/{id}/offers
     */
    public function offers(Request $request, string $id): JsonResponse
    {
        $language = $request->get('language', 'ar');
        $merchant = Merchant::where('id', $id)->where('approved', true)->firstOrFail();

        $offers = $this->merchantOffersForRequest($merchant, $request)
            ->with(['category:id,name_ar,name_en', 'mall:id,name,name_ar,name_en'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $data = $offers->getCollection()->map(fn ($offer) => $this->formatOfferMinimal($offer, $language))->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }
}
