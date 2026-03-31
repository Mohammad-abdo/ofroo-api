<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Coupon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(): array
    {
        $cacheKey = 'dashboard_stats';
        $ttl = 300;

        return Cache::remember($cacheKey, $ttl, function () {
            return [
                'users' => [
                    'total' => User::count(),
                    'new_today' => User::whereDate('created_at', today())->count(),
                    'new_this_week' => User::whereDate('created_at', '>=', now()->startOfWeek())->count(),
                    'new_this_month' => User::whereDate('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'merchants' => [
                    'total' => Merchant::count(),
                    'approved' => Merchant::where('approved', true)->count(),
                    'pending' => Merchant::where('approved', false)->count(),
                    'new_today' => Merchant::whereDate('created_at', today())->count(),
                ],
                'offers' => [
                    'total' => Offer::count(),
                    'active' => Offer::where('status', 'active')->count(),
                    'pending' => Offer::where('status', 'pending')->count(),
                    'expired' => Offer::where('status', 'expired')->count(),
                ],
                'orders' => [
                    'total' => Order::count(),
                    'paid' => Order::where('payment_status', 'paid')->count(),
                    'pending' => Order::where('payment_status', 'pending')->count(),
                    'total_revenue' => Order::where('payment_status', 'paid')->sum('total_amount'),
                    'today_revenue' => Order::where('payment_status', 'paid')
                        ->whereDate('created_at', today())
                        ->sum('total_amount'),
                ],
                'coupons' => [
                    'total' => Coupon::count(),
                    'active' => Coupon::where('status', 'active')->count(),
                    'used' => Coupon::where('status', 'used')->count(),
                    'reserved' => Coupon::where('status', 'reserved')->count(),
                ],
            ];
        });
    }

    public function getRecentOrders(int $limit = 10): array
    {
        return Order::with(['user', 'merchant'])
            ->where('payment_status', 'paid')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'user_name' => $order->user?->name ?? 'N/A',
                'merchant_name' => $order->merchant?->company_name ?? 'N/A',
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    public function getTopMerchants(int $limit = 10): array
    {
        return Merchant::select('merchants.*')
            ->selectRaw('COALESCE(SUM(orders.total_amount), 0) as total_sales')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->leftJoin('orders', function ($join) {
                $join->on('merchants.id', '=', 'orders.merchant_id')
                    ->where('orders.payment_status', 'paid');
            })
            ->groupBy('merchants.id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get()
            ->map(fn ($merchant) => [
                'id' => $merchant->id,
                'name' => $merchant->company_name ?? 'N/A',
                'total_sales' => (float) $merchant->total_sales,
                'orders_count' => (int) $merchant->orders_count,
            ])
            ->toArray();
    }

    public function clearCache(): void
    {
        Cache::forget('dashboard_stats');
    }
}
