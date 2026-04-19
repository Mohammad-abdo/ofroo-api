<?php

namespace App\Repositories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

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

        if (! empty($filters['mobile_public'])) {
            $query->mobilePubliclyAvailable();
        } elseif (isset($filters['active']) && $filters['active']) {
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

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = "%{$search}%";
            $offerCols = array_values(array_filter(
                ['title', 'title_ar', 'title_en', 'description', 'description_ar', 'description_en'],
                fn ($c) => Schema::hasColumn('offers', $c)
            ));
            $merchantCols = array_values(array_filter(
                ['company_name', 'company_name_ar', 'company_name_en'],
                fn ($c) => Schema::hasColumn('merchants', $c)
            ));

            $query->where(function ($q) use ($offerCols, $merchantCols, $like) {
                foreach ($offerCols as $i => $col) {
                    $i === 0 ? $q->where($col, 'like', $like) : $q->orWhere($col, 'like', $like);
                }
                if (! empty($merchantCols)) {
                    $q->orWhereHas('merchant', function ($mq) use ($merchantCols, $like) {
                        foreach ($merchantCols as $i => $col) {
                            $i === 0 ? $mq->where($col, 'like', $like) : $mq->orWhere($col, 'like', $like);
                        }
                    });
                }
            });
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
