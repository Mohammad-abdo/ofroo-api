<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $categories = Category::whereNull('parent_id')
            ->select('id', 'name_ar', 'name_en', 'order_index')
            ->orderBy('order_index')
            ->get();

        $data = $categories->map(function ($category) use ($language) {
            return [
                'id' => $category->id,
                'name' => $language === 'ar'
                    ? ($category->name_ar ?? $category->name_en)
                    : ($category->name_en ?? $category->name_ar),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * List all categories with active offers in children (mobile API).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $language = $request->get('language', $user ? $user->language : null) ?? 'ar';

        $categories = Category::whereNull('parent_id')
            ->with(['offers' => fn ($q) => $q->where('status', 'active')->whereNotNull('category_id')->with(['merchant', 'category', 'branches', 'coupons'])])
            ->orderBy('order_index')
            ->get();

        $data = $categories->map(function ($category) use ($language, $request) {
            // Only offers that belong to this category (category_id = this category id)
            $offersForCategory = $category->offers->where('category_id', $category->id)->values();
            $children = $offersForCategory->map(fn ($offer) => (new OfferResource($offer))->toArray($request))->all();

            return [
                'id' => $category->id,
                'name' => $language === 'ar' ? ($category->name_ar ?? $category->name_en) : ($category->name_en ?? $category->name_ar),
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'order_index' => $category->order_index,
                'image' => $category->image_url,
                'children' => $children,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get category details with its offers as children (mobile API).
     * Returns category info + image + children = array of offers (full OfferResource format).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $language = $request->get('language', $user ? $user->language : null) ?? 'ar';

        $category = Category::with([
            'offers' => function ($q) {
                $q->where('status', 'active')
                    ->whereNotNull('category_id')
                    ->with(['merchant', 'category', 'branches', 'coupons']);
            },
        ])->findOrFail($id);

        $offersForCategory = $category->offers->where('category_id', $category->id)->values();
        $children = $offersForCategory->map(function ($offer) use ($request) {
            return (new OfferResource($offer))->toArray($request);
        })->all();

        $name = $language === 'ar'
            ? ($category->name_ar ?? $category->name_en)
            : ($category->name_en ?? $category->name_ar);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name' => $name,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'order_index' => $category->order_index,
                'image' => $category->image_url,
                'children' => $children,
            ],
        ]);
    }
}
