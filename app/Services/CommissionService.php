<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Merchant;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function getCommissionRate(): float
    {
        return FeatureFlagService::getCommissionRate();
    }

    public function getCommissionsSummary(): array
    {
        $totalCommission = Commission::where('status', 'completed')->sum('commission_amount');
        $thisMonth = Commission::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('commission_amount');
        $lastMonth = Commission::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('commission_amount');

        $totalOrders = Commission::where('status', 'completed')->count();
        $avgCommission = $totalOrders > 0 ? $totalCommission / $totalOrders : 0;

        return [
            'total_commission' => (float) $totalCommission,
            'this_month' => (float) $thisMonth,
            'last_month' => (float) $lastMonth,
            'growth_percent' => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2) : 0,
            'total_orders' => $totalOrders,
            'average_commission' => round($avgCommission, 2),
        ];
    }

    public function getCommissionsByMerchant(int $perPage = 15): array
    {
        $commissions = Commission::with(['merchant.user', 'order'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => $commissions->items(),
            'meta' => [
                'current_page' => $commissions->currentPage(),
                'last_page' => $commissions->lastPage(),
                'per_page' => $commissions->perPage(),
                'total' => $commissions->total(),
            ],
        ];
    }

    public function getCommissionsByMerchantGrouped(int $perPage = 15): array
    {
        $results = Merchant::select('merchants.*')
            ->selectRaw('SUM(commissions.commission_amount) as total_commission')
            ->selectRaw('COUNT(commissions.id) as orders_count')
            ->selectRaw('AVG(commissions.commission_rate) as avg_rate')
            ->leftJoin('commissions', function ($join) {
                $join->on('merchants.id', '=', 'commissions.merchant_id')
                    ->where('commissions.status', 'completed');
            })
            ->groupBy('merchants.id')
            ->having('total_commission', '>', 0)
            ->orderByDesc('total_commission')
            ->paginate($perPage);

        return [
            'data' => $results->map(function ($merchant) {
                return [
                    'merchant_id' => $merchant->id,
                    'company_name' => $merchant->company_name,
                    'total_commission' => (float) ($merchant->total_commission ?? 0),
                    'orders_count' => (int) ($merchant->orders_count ?? 0),
                    'average_rate' => (float) ($merchant->avg_rate ?? 0) * 100,
                ];
            })->toArray(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ];
    }

    public function getMonthlyCommission(int $months = 6): array
    {
        $data = [];
        for ($i = 0; $i < $months; $i++) {
            $month = now()->subMonths($i);
            $commission = Commission::where('status', 'completed')
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->sum('commission_amount');

            $data[] = [
                'month' => $month->format('M Y'),
                'month_num' => $month->month,
                'year' => $month->year,
                'commission' => (float) $commission,
            ];
        }

        return array_reverse($data);
    }

    public function getCommissionsForExport(array $filters = []): array
    {
        $query = Commission::with(['merchant.user', 'order'])
            ->where('status', 'completed');

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->get()->map(function ($commission) {
            return [
                'id' => $commission->id,
                'order_id' => $commission->order_id,
                'merchant_name' => $commission->merchant?->company_name ?? 'N/A',
                'order_amount' => $commission->order?->total_amount ?? 0,
                'commission_rate' => round($commission->commission_rate * 100, 2),
                'commission_amount' => $commission->commission_amount,
                'date' => $commission->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();
    }
}
