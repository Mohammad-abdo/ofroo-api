<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ImageUploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Update category order
     */
    public function updateCategoryOrder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.order_index' => 'required|integer',
        ]);

        foreach ($request->categories as $categoryData) {
            Category::where('id', $categoryData['id'])
                ->update(['order_index' => $categoryData['order_index']]);
        }

        return response()->json([
            'message' => 'Category order updated successfully',
        ]);
    }

    /**
     * Get all categories (Admin)
     */
    public function getCategories(Request $request): JsonResponse
    {
        $query = Category::with(['parent', 'children'])
            ->withCount([
                'merchants',
                'couponsViaOffers as coupons_count',
            ]);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('order_index')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get single category (Admin)
     */
    public function getCategory(string $id): JsonResponse
    {
        $category = Category::with(['parent', 'children', 'offers'])
            ->withCount([
                'merchants',
                'couponsViaOffers as coupons_count',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Create category (Admin)
     */
    public function createCategory(Request $request): JsonResponse
    {
        $rules = [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'order_index' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = ImageUploadRules::fileMax(2048);
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en ?? $request->name_ar,
            'parent_id' => $request->parent_id,
            'order_index' => (int) ($request->order_index ?? 0),
            'is_active' => true,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $data['image'] = $file->storeAs('categories', $name, 'public');
        }

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Update category (Admin)
     */
    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $rules = [
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'order_index' => 'nullable|integer',
            'remove_image' => 'nullable|string|in:1,true',
            'is_active' => 'nullable|boolean',

        ];
        if ($request->hasFile('image')) {
            $rules['image'] = ImageUploadRules::fileMax(2048);
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('parent_id') && $request->parent_id == $id) {
            return response()->json([
                'message' => 'Category cannot be its own parent',
            ], 422);
        }

        $update = [
            'name_ar' => $request->input('name_ar', $category->name_ar),
            'name_en' => $request->input('name_en', $category->name_en),
            'parent_id' => $request->has('parent_id') ? $request->parent_id : $category->parent_id,
            'order_index' => $request->has('order_index') ? (int) $request->order_index : $category->order_index,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ];

        if ($request->input('remove_image') === '1' || $request->input('remove_image') === 'true') {
            if ($category->image && ! (str_starts_with($category->image, 'http://') || str_starts_with($category->image, 'https://'))) {
                Storage::disk('public')->delete($category->image);
            }
            $update['image'] = null;
        }

        if ($request->hasFile('image')) {
            if ($category->image && ! (str_starts_with($category->image, 'http://') || str_starts_with($category->image, 'https://'))) {
                Storage::disk('public')->delete($category->image);
            }
            $file = $request->file('image');
            $name = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $update['image'] = $file->storeAs('categories', $name, 'public');
        }

        $category->update($update);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Delete category (Admin)
     */
    public function deleteCategory(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories. Please delete subcategories first.',
            ], 422);
        }

        if ($category->offers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with offers. Please delete or move offers first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
