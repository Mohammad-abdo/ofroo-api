<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories
     */
    public function index(Request $request): JsonResponse
    {
        $language = $request->user()?->language ?? $request->get('language', 'ar');
        
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->orderBy('order_index')
            ->get()
            ->map(function ($category) use ($language) {
                return [
                    'id' => $category->id,
                    'name' => $language === 'ar' ? $category->name_ar : $category->name_en,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name_en,
                    'order_index' => $category->order_index,
                    'children' => $category->children->map(function ($child) use ($language) {
                        return [
                            'id' => $child->id,
                            'name' => $language === 'ar' ? $child->name_ar : $child->name_en,
                            'name_ar' => $child->name_ar,
                            'name_en' => $child->name_en,
                            'order_index' => $child->order_index,
                        ];
                    }),
                ];
            });

        return response()->json([
            'data' => $categories,
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
