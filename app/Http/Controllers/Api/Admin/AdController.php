<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Support\ImageUploadRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    // ==================== Ads Management ====================

    /**
     * Apply shared filters for admin ads listing / report stats.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function applyAdminAdFilters(Request $request, $query, bool $applyLifecycle = true): void
    {
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        if ($request->filled('ad_type')) {
            $query->where('ad_type', $request->ad_type);
        }

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%");
            });
        }

        if ($applyLifecycle && $request->filled('lifecycle')) {
            match ($request->lifecycle) {
                'active' => $query->where('is_active', true)->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>', now());
                }),
                'expired' => $query->where('is_active', true)
                    ->whereNotNull('end_date')
                    ->where('end_date', '<=', now()),
                'pending' => $query->where('is_active', false),
                default => null,
            };
        }
    }

    /**
     * Aggregates + top ads for the reports dashboard (same filters as getAds, respects lifecycle).
     */
    public function getAdsReportStats(Request $request): JsonResponse
    {
        $filtered = Ad::query();
        $this->applyAdminAdFilters($request, $filtered, true);

        $total = (clone $filtered)->count();
        $viewsSum = (int) (clone $filtered)->sum('views_count');
        $clicksSum = (int) (clone $filtered)->sum('clicks_count');

        $unscoped = Ad::query();
        $this->applyAdminAdFilters($request, $unscoped, false);

        $active = (clone $unscoped)->where('is_active', true)->where(function ($q) {
            $q->whereNull('end_date')->orWhere('end_date', '>', now());
        })->count();

        $expired = (clone $unscoped)->where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now())
            ->count();

        $pending = (clone $unscoped)->where('is_active', false)->count();

        $topByClicks = (clone $filtered)
            ->orderByDesc('clicks_count')
            ->limit(8)
            ->get([
                'id', 'title', 'title_ar', 'title_en', 'views_count', 'clicks_count',
                'ad_type', 'is_active', 'start_date', 'end_date',
            ]);

        return response()->json([
            'data' => [
                'total' => $total,
                'views_sum' => $viewsSum,
                'clicks_sum' => $clicksSum,
                'active' => $active,
                'expired' => $expired,
                'pending' => $pending,
                'top_by_clicks' => $topByClicks,
            ],
        ]);
    }

    /**
     * Get all ads (Admin)
     */
    public function getAds(Request $request): JsonResponse
    {
        $query = Ad::with(['merchant', 'category']);
        $this->applyAdminAdFilters($request, $query, true);

        $ads = $query->orderBy('order_index')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $ads->getCollection(),
            'meta' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }

    /**
     * Get single ad (Admin)
     */
    public function getAd(string $id): JsonResponse
    {
        $ad = Ad::with(['merchant', 'category'])
            ->findOrFail($id);

        return response()->json([
            'data' => $ad,
        ]);
    }

    /**
     * Create ad (Admin)
     */
    public function createAd(Request $request): JsonResponse
    {
        $input = $request->all();
        // Accept "budget" from frontend as total_budget
        if ($request->has('budget') && ! $request->filled('total_budget')) {
            $input['total_budget'] = $request->budget;
        }
        // Treat empty date strings as null so validation passes
        foreach (['start_date', 'end_date'] as $dateField) {
            if (isset($input[$dateField]) && (is_string($input[$dateField]) && trim($input[$dateField]) === '')) {
                $input[$dateField] = null;
            }
        }
        // Ensure URL fields are strings or null (frontend may send null / non-scalar)
        foreach (['video_url', 'link_url', 'image_url'] as $urlField) {
            if (! array_key_exists($urlField, $input)) {
                continue;
            }
            $v = $input[$urlField];
            if ($v === null || $v === '') {
                $input[$urlField] = null;
            } elseif (! is_string($v)) {
                $input[$urlField] = is_scalar($v) ? (string) $v : null;
            }
        }
        $request->merge($input);
        $validator = Validator::make($input, [
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            // Support both file upload and URL
            'image' => ImageUploadRules::nullableFileMax(10240), // 10MB max
            'video' => 'nullable|file|mimes:mp4,avi,mov,webm|max:51200', // 50MB max for videos
            'image_url' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'link_url' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'ad_type' => 'required|in:banner,video',
            'merchant_id' => 'nullable|exists:merchants,id',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'nullable',
            'order_index' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'cost_per_click' => 'nullable|numeric|min:0',
            'total_budget' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            // إضافة دعم الإحصائيات عند الإنشاء
            'views_count' => 'nullable|integer|min:0',
            'clicks_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('start_date') && $request->filled('end_date') && $request->end_date <= $request->start_date) {
            return response()->json([
                'message' => 'End date must be after start date',
            ], 422);
        }

        // Handle file uploads
        $imageUrl = $request->image_url;
        $videoUrl = $request->video_url;

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageName = time().'_'.uniqid().'.'.$imageFile->getClientOriginalExtension();
            // مسار بدون كلمة "ads" لتجنب حجب مانعات الإعلانات (ERR_BLOCKED_BY_CLIENT)
            $imagePath = $imageFile->storeAs('promo/images', $imageName, 'public');
            $imageUrl = asset('storage/'.$imagePath);
        }

        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $videoName = time().'_'.uniqid().'.'.$videoFile->getClientOriginalExtension();
            $videoPath = $videoFile->storeAs('promo/videos', $videoName, 'public');
            $videoUrl = asset('storage/'.$videoPath);
        }

        // Validate that we have either image or video based on ad_type
        if ($request->ad_type === 'video' && ! $videoUrl) {
            return response()->json([
                'message' => 'Video file or video URL is required for video ads',
            ], 422);
        }

        if ($request->ad_type === 'banner' && ! $imageUrl) {
            return response()->json([
                'message' => 'Image file or image URL is required for banner ads',
            ], 422);
        }

        // Video ads: poster image is optional — leave image_url null when not provided (column is nullable).
        if ($request->ad_type === 'video' && ($imageUrl === null || $imageUrl === '')) {
            $imageUrl = null;
        }

        $totalBudget = $request->filled('total_budget') ? $request->total_budget : $request->input('budget');
        $ad = Ad::create([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image_url' => $imageUrl,
            'video_url' => $videoUrl,
            'images' => $request->images,
            'link_url' => $request->link_url,
            'position' => $request->position,
            'ad_type' => $request->ad_type,
            'merchant_id' => $request->merchant_id,
            'category_id' => $request->category_id,
            'is_active' => $request->boolean('is_active', true),
            'order_index' => $request->order_index ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'cost_per_click' => $request->cost_per_click,
            'total_budget' => $totalBudget,
        ]);

        return response()->json([
            'message' => 'Ad created successfully',
            'data' => $ad->load(['merchant', 'category']),
        ], 201);
    }

    /**
     * Update ad (Admin)
     */
    public function updateAd(Request $request, string $id): JsonResponse
    {
        $ad = Ad::findOrFail($id);

        $merge = [];
        foreach (['video_url', 'link_url', 'image_url'] as $urlField) {
            if (! $request->has($urlField)) {
                continue;
            }
            $v = $request->input($urlField);
            if ($v === null || $v === '') {
                $merge[$urlField] = null;
            } elseif (! is_string($v)) {
                $merge[$urlField] = is_scalar($v) ? (string) $v : null;
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }

        if ($request->filled('end_date') && ! $request->filled('start_date') && $ad->start_date) {
            $request->merge(['start_date' => $ad->start_date->format('Y-m-d H:i:s')]);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'description_en' => 'sometimes|string',
            // Support both file upload and URL for updates
            'image' => ImageUploadRules::sometimesFileMax(10240),
            'video' => 'sometimes|file|mimes:mp4,avi,mov,webm|max:51200',
            'image_url' => 'sometimes|nullable|string|max:500',
            'video_url' => 'sometimes|nullable|string|max:500',
            'images' => 'sometimes|array',
            'link_url' => 'sometimes|nullable|string|max:500',
            'position' => 'sometimes|string|max:50',
            'ad_type' => 'sometimes|in:banner,video',
            'merchant_id' => 'sometimes|nullable|exists:merchants,id',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date|after:start_date',
            'cost_per_click' => 'sometimes|numeric|min:0',
            'total_budget' => 'sometimes|numeric|min:0',
            'budget' => 'sometimes|numeric|min:0',
            // إضافة دعم تحديث الإحصائيات
            'views_count' => 'sometimes|integer|min:0',
            'clicks_count' => 'sometimes|integer|min:0',
        ]);

        $updateData = collect($validated)
            ->except(['image', 'video'])
            ->all();

        if ($request->filled('budget') && ! $request->filled('total_budget')) {
            $updateData['total_budget'] = $request->input('budget');
        }

        // Handle file uploads for updates (مسار promo لتجنب حجب مانعات الإعلانات)
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageName = time().'_'.uniqid().'.'.$imageFile->getClientOriginalExtension();
            $imagePath = $imageFile->storeAs('promo/images', $imageName, 'public');
            $updateData['image_url'] = asset('storage/'.$imagePath);
        }

        if ($request->hasFile('video')) {
            $videoFile = $request->file('video');
            $videoName = time().'_'.uniqid().'.'.$videoFile->getClientOriginalExtension();
            $videoPath = $videoFile->storeAs('promo/videos', $videoName, 'public');
            $updateData['video_url'] = asset('storage/'.$videoPath);
        }

        $ad->update($updateData);

        return response()->json([
            'message' => 'Ad updated successfully',
            'data' => $ad->fresh()->load(['merchant', 'category']),
        ]);
    }

    /**
     * Delete ad (Admin)
     */
    public function deleteAd(string $id): JsonResponse
    {
        $ad = Ad::findOrFail($id);
        $ad->delete();

        return response()->json([
            'message' => 'Ad deleted successfully',
        ]);
    }

    /**
     * Get all banners (Admin)
     */
    public function getBanners(Request $request): JsonResponse
    {
        $query = Ad::where('ad_type', 'banner');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min(200, (int) $request->get('per_page', 100)));
        $banners = $query->orderBy('order_index')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Same shape as other admin list endpoints — avoids clients mistaking paginator `links` for rows.
        return response()->json([
            'data' => $banners->items(),
            'meta' => [
                'current_page' => $banners->currentPage(),
                'last_page' => $banners->lastPage(),
                'per_page' => $banners->perPage(),
                'total' => $banners->total(),
            ],
        ]);
    }

    /**
     * Create banner (Admin)
     */
    public function createBanner(Request $request): JsonResponse
    {
        $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
        $validator = Validator::make($request->all(), [
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => ImageUploadRules::requiredFileMax($maxKb),
            'link_url' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
            'order_index' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imageUrl = '';
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'banner_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('banners', $fileName, 'public');
            $imageUrl = asset('storage/'.$path);
        }

        $startDate = $request->filled('start_date') ? $request->date('start_date')->startOfDay() : null;
        $endDate = $request->filled('end_date') ? $request->date('end_date')->endOfDay() : null;

        $banner = Ad::create([
            'title' => $request->title_en,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image_url' => $imageUrl,
            'link_url' => $request->link_url,
            'position' => $request->position,
            'ad_type' => 'banner',
            'is_active' => $request->boolean('is_active', true),
            'order_index' => $request->order_index ?? 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return response()->json([
            'message' => 'Banner created successfully',
            'data' => $banner,
        ], 201);
    }

    /**
     * Update banner (Admin)
     */
    public function updateBanner(Request $request, string $id): JsonResponse
    {
        $banner = Ad::where('ad_type', 'banner')->findOrFail($id);

        $maxKb = (int) config('app.max_admin_image_upload_kb', 131072);
        $validator = Validator::make($request->all(), [
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description_ar' => 'sometimes|string',
            'description_en' => 'sometimes|string',
            'image' => ImageUploadRules::sometimesFileMax($maxKb),
            'link_url' => 'sometimes|string|max:500',
            'position' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
            'order_index' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->except(['image', '_method']);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = 'banner_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('banners', $fileName, 'public');
            $data['image_url'] = asset('storage/'.$path);
        }

        if (isset($data['title_en'])) {
            $data['title'] = $data['title_en'];
        }

        if (array_key_exists('start_date', $data)) {
            $data['start_date'] = $request->filled('start_date') ? $request->date('start_date')->startOfDay() : null;
        }
        if (array_key_exists('end_date', $data)) {
            $data['end_date'] = $request->filled('end_date') ? $request->date('end_date')->endOfDay() : null;
        }

        $banner->update($data);

        return response()->json([
            'message' => 'Banner updated successfully',
            'data' => $banner->fresh(),
        ]);
    }

    /**
     * Delete banner (Admin)
     */
    public function deleteBanner(string $id): JsonResponse
    {
        $banner = Ad::where('ad_type', 'banner')->findOrFail($id);
        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted successfully',
        ]);
    }
}
