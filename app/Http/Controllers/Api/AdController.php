<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdController extends Controller
{
    /**
     * Full URL for a path (storage or relative).
     */
    private function fullUrl(?string $path): string
    {
        if (empty($path)) {
            return '';
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        return rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Build single ad payload with all related data (for list and show).
     */
    private function adPayload(Ad $ad): array
    {
        $imageUrl = $ad->image_url ? $this->fullUrl($ad->image_url) : null;
        $videoUrl = $ad->video_url ? $this->fullUrl($ad->video_url) : null;
        $images = [];
        if (! empty($ad->images) && is_array($ad->images)) {
            foreach ($ad->images as $img) {
                $images[] = $this->fullUrl(is_string($img) ? $img : ($img['url'] ?? $img));
            }
        }

        $merchant = null;
        if ($ad->relationLoaded('merchant') && $ad->merchant) {
            $m = $ad->merchant;
            $merchant = [
                'id' => $m->id,
                'company_name' => $m->company_name ?? $m->company_name_ar ?? $m->company_name_en ?? '',
                'company_name_ar' => $m->company_name_ar ?? '',
                'company_name_en' => $m->company_name_en ?? '',
                'logo_url' => $m->logo_url ? $this->fullUrl($m->logo_url) : null,
                'description' => $m->description ?? $m->description_ar ?? $m->description_en ?? null,
            ];
        }

        $category = null;
        if ($ad->relationLoaded('category') && $ad->category) {
            $c = $ad->category;
            $category = [
                'id' => $c->id,
                'name_ar' => $c->name_ar ?? '',
                'name_en' => $c->name_en ?? '',
                'image' => $c->image ?? null,
                'image_url' => $c->image_url ?? $this->fullUrl($c->image ?? null),
            ];
        }

        return [
            'id' => $ad->id,
            'title' => $ad->title ?? $ad->title_ar ?? $ad->title_en ?? '',
            'title_ar' => $ad->title_ar ?? '',
            'title_en' => $ad->title_en ?? '',
            'description' => $ad->description ?? $ad->description_ar ?? $ad->description_en ?? '',
            'description_ar' => $ad->description_ar ?? '',
            'description_en' => $ad->description_en ?? '',
            'image_url' => $imageUrl,
            'video_url' => $videoUrl,
            'images' => $images,
            'link_url' => $ad->link_url ?? null,
            'position' => $ad->position ?? null,
            'ad_type' => $ad->ad_type ?? null,
            'order_index' => (int) ($ad->order_index ?? 0),
            'start_date' => $ad->start_date?->toIso8601String(),
            'end_date' => $ad->end_date?->toIso8601String(),
            'status' => $ad->status ?? 'active',
            'merchant' => $merchant,
            'category' => $category,
            'created_at' => $ad->created_at?->toIso8601String(),
            'updated_at' => $ad->updated_at?->toIso8601String(),
        ];
    }

    /**
     * GET /api/mobile/ads
     * List ads for mobile (active only, within date range). Returns all ad data + merchant + category.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ad::with(['merchant', 'category'])
            ->where('is_active', true);

        $now = now();
        $query->where(function ($q) use ($now) {
            $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
        });
        $query->where(function ($q) use ($now) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
        });

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

        $perPage = max(1, min(50, (int) $request->get('per_page', 15)));
        $ads = $query->orderBy('order_index')->orderBy('created_at', 'desc')->paginate($perPage);

        $data = $ads->getCollection()->map(fn (Ad $ad) => $this->adPayload($ad));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ]);
    }

    /**
     * GET /api/mobile/ads/{id}
     * Single ad with all related data.
     */
    public function show(string $id): JsonResponse
    {
        $ad = Ad::with(['merchant', 'category'])->findOrFail($id);
        return response()->json(['data' => $this->adPayload($ad)]);
    }
}
