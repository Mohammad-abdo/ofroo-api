<?php

namespace App\Repositories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OfferRepository extends BaseRepository
{
    public function __construct(Offer $model)
    {
        parent::__construct($model);
    }

    public function getOffers(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['merchant', 'category', 'mall', 'branches', 'coupons']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['active']) && $filters['active']) {
            $query->active();
        }

        if (isset($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        if (isset($filters['mall'])) {
            $query->where('mall_id', $filters['mall']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where('title', 'like', "%{$search}%");
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getNearbyOffers(float $lat, float $lng, float $distanceKm = 10): Collection
    {
        // This is simplified, real implementation might use spatial functions or Haversine
        return $this->model->active()
            ->with(['merchant', 'category', 'mall', 'branches', 'coupons'])
            ->get();
    }

    public function getByMerchant(int $merchantId): Collection
    {
        return $this->model->where('merchant_id', $merchantId)
            ->with(['category', 'mall', 'branches', 'coupons'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
