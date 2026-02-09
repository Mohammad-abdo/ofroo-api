<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['items.offer', 'coupons', 'merchant'])
            ->where('user_id', $userId);

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByMerchant(int $merchantId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'items.offer', 'coupons'])
            ->where('merchant_id', $merchantId)
            ->where('payment_status', 'paid');

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}


