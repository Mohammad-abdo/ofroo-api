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
     * List all categories with active offers in children (mobile API).
     */
    public function index(Request $request): JsonResponse
    {
        $language = $request->get('language', $request->user()?->language ?? 'ar');

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
     * Get category details
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::with(['parent', 'children'])->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'order_index' => $category->order_index,
                'parent' => $category->parent,
                'children' => $category->children,
            ],
        ]);
    }
}
