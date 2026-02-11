<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Merchant;
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
     * صفحة تفاصيل التاجر: بيانات التاجر + عروض مختصرة.
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

        $rating = (float) $merchant->reviews()->where('visible_to_public', true)->avg('rating');
        $reviewsCount = $merchant->reviews()->where('visible_to_public', true)->count();

        $categoryName = '';
        if ($merchant->category) {
            $categoryName = $language === 'ar'
                ? ($merchant->category->name_ar ?? $merchant->category->name_en)
                : ($merchant->category->name_en ?? $merchant->category->name_ar);
        }

        $offers = $merchant->offers()
            ->where('status', 'active')
            ->with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->orderBy('created_at', 'desc')
            ->limit(self::OFFERS_PER_DETAIL_PAGE)
            ->get();

        $offersData = $offers->map(function ($offer) use ($request) {
            return (new OfferResource($offer))->toArray($request);
        })->all();

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
            'logo_url' => $merchant->logo_url ?? '',
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
            'social_links' => [], // يمكن إضافتها لاحقاً من جدول أو إعدادات
            'offers' => $offersData,
            'offers_total' => $merchant->offers()->where('status', 'active')->count(),
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * كل عروض التاجر (مع pagination) - "عرض الكل".
     * GET /api/mobile/merchants/{id}/offers
     */
    public function offers(Request $request, string $id): JsonResponse
    {
        $merchant = Merchant::where('id', $id)->where('approved', true)->firstOrFail();

        $offers = $merchant->offers()
            ->where('status', 'active')
            ->with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $data = $offers->getCollection()->map(function ($offer) use ($request) {
            return (new OfferResource($offer))->toArray($request);
        })->all();

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
