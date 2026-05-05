<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * قائمة أسماء التصنيفات فقط (id + name) بدون children - للموبايل.
     * GET api/mobile/category-name?language=ar|en
     */
    public function categoryName(Request $request): JsonResponse
    {
        $user = $request->user();
        $language = $request->get('language', $user ? $user->language : null) ?? 'ar';
        $isMobile = $request->is('api/mobile/*');

        $query = Category::query()
            ->whereNull('parent_id')
            ->select('id', 'name_ar', 'name_en', 'order_index', 'is_active');
        if ($isMobile) {
            $query->where('is_active', true);
        }
        $categories = $query->orderBy('order_index')->get();

        $data = $categories->map(function ($category) use ($language, $isMobile) {
            $active = (bool) ($category->is_active ?? false);

            return [
                'id' => $category->id,
                'name' => $language === 'ar'
                    ? ($category->name_ar ?? $category->name_en)
                    : ($category->name_en ?? $category->name_ar),
                // Mobile (Flutter): many models expect String, not bool, for this field.
                'is_active' => $isMobile ? ($active ? '1' : '0') : $active,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Minimal `{ id, name }` list for pickers (e.g. mall details filters: merchant_category_id, offer_category_id).
     * Same category roots apply to merchants and offers in this project.
     *
     * GET /api/mobile/categories/filter-options?language=ar|en
     * GET /api/mobile/merchant-categories/options
     * GET /api/mobile/offer-categories/options
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $language = $request->get('language', $user ? $user->language : null) ?? 'ar';
        $isMobile = $request->is('api/mobile/*');

        $query = Category::query()
            ->whereNull('parent_id')
            ->select(['id', 'name_ar', 'name_en', 'order_index'])
            ->orderBy('order_index');

        if ($isMobile) {
            $query->where('is_active', true);
        }

        $data = $query->get()->map(function (Category $category) use ($language) {
            return [
                'id' => (int) $category->id,
                'name' => $language === 'ar'
                    ? (string) ($category->name_ar ?? $category->name_en ?? '')
                    : (string) ($category->name_en ?? $category->name_ar ?? ''),
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * List all categories with active offers in children (mobile API).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $language = $request->get('language', $user ? $user->language : null) ?? 'ar';
        $isMobile = $request->is('api/mobile/*');

        // Mobile: never cache — admin toggles is_active must show immediately.
        // Web: cache 1h (public /api/categories).
        if ($isMobile) {
            $data = $this->buildCategoriesWithOffersIndex($request, $language, true);
        } else {
            $cacheKey = 'categories_with_offers_'.$language.'_web';
            $data = Cache::remember($cacheKey, 3600, fn () => $this->buildCategoriesWithOffersIndex($request, $language, false));
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Invalidate cached public web category trees (supported UI languages).
     */
    public static function forgetPublicCategoriesListCache(): void
    {
        foreach (['ar', 'en'] as $lang) {
            Cache::forget('categories_with_offers_'.$lang.'_web');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildCategoriesWithOffersIndex(Request $request, string $language, bool $isMobile): array
    {
        $root = Category::query()->whereNull('parent_id');
        if ($isMobile) {
            $root->where('is_active', true);
        }
        $categories = $root
            ->with(['offers' => function ($q) use ($isMobile) {
                if ($isMobile) {
                    $q->mobilePubliclyAvailable();
                } else {
                    $q->where('status', 'active');
                }
                $q->whereNotNull('category_id')->with(['merchant', 'category', 'branches', 'coupons']);
            }])
            ->orderBy('order_index')
            ->get();

        return $categories->map(function ($category) use ($language, $request, $isMobile) {
            $offersForCategory = $category->offers->where('category_id', $category->id)->values();
            $children = $offersForCategory->map(fn ($offer) => (new OfferResource($offer))->toArray($request))->all();
            $active = (bool) ($category->is_active ?? false);

            return [
                'id' => $category->id,
                'name' => $language === 'ar' ? ($category->name_ar ?? $category->name_en) : ($category->name_en ?? $category->name_ar),
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'order_index' => $category->order_index,
                'image' => $category->image_url,
                'children' => $children,
                'is_active' => $isMobile ? ($active ? '1' : '0') : $active,
            ];
        })->values()->all();
    }

    /**
     * Get category details with its offers as children (mobile API).
     * Returns category info + image + children = array of offers (full OfferResource format).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $language = $request->get('language', $user ? $user->language : null) ?? 'ar';

        $isMobile = $request->is('api/mobile/*');
        $categoryQuery = Category::query()->with([
            'offers' => function ($q) use ($isMobile) {
                if ($isMobile) {
                    $q->mobilePubliclyAvailable();
                } else {
                    $q->where('status', 'active');
                }
                $q->whereNotNull('category_id')
                    ->with(['merchant', 'category', 'branches', 'coupons']);
            },
        ]);
        if ($isMobile) {
            $categoryQuery->where('is_active', true);
        }
        $category = $categoryQuery->findOrFail($id);

        $offersForCategory = $category->offers->where('category_id', $category->id)->values();
        $children = $offersForCategory->map(function ($offer) use ($request) {
            return (new OfferResource($offer))->toArray($request);
        })->values()->all();

        $name = $language === 'ar'
            ? ($category->name_ar ?? $category->name_en)
            : ($category->name_en ?? $category->name_ar);

        $active = (bool) ($category->is_active ?? false);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $name,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'order_index' => $category->order_index,
                'image' => $category->image_url,
                'children' => $children,
                'is_active' => $isMobile ? ($active ? '1' : '0') : $active,
            ],
        ]);
    }

    /**
     * Mobile-friendly endpoint: offers list for a category.
     * GET /api/mobile/categories/{id}/offers
     */
    public function offers(Request $request, string $id): JsonResponse
    {
        $isMobile = $request->is('api/mobile/*');

        $categoryQuery = Category::query();
        if ($isMobile) {
            $categoryQuery->where('is_active', true);
        }
        $category = $categoryQuery->findOrFail($id);

        $q = $category->offers()
            ->whereNotNull('category_id')
            ->when($isMobile, fn ($qq) => $qq->mobilePubliclyAvailable(), fn ($qq) => $qq->where('status', 'active'))
            ->with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->orderByDesc('id');

        $perPage = max(1, min(50, (int) $request->get('per_page', 15)));
        $page = $q->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($offer) => (new OfferResource($offer))->toArray($request))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'category_id' => (int) $category->id,
            ],
        ]);
    }
}
