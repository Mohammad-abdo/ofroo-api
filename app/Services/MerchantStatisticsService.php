<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MerchantStatisticsService
{
    public function getStatistics(Merchant $merchant): array
    {
        $baseQuery = Order::where('merchant_id', $merchant->id)
            ->where('payment_status', 'paid');

        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $todayOrders = (clone $baseQuery)->where('created_at', '>=', $today);
        $weeklyOrders = (clone $baseQuery)->where('created_at', '>=', $weekStart);
        $allOrders = $baseQuery;

        $totalOrders = $allOrders->count();
        $totalRevenue = $allOrders->sum('total_amount');
        $averageOrderValue = $totalOrders > 0 ? $totalOrders / $totalRevenue : 0;

        $todayRevenue = $todayOrders->sum('total_amount');
        $weeklyRevenue = $weeklyOrders->sum('total_amount');

        $commissionRate = FeatureFlagService::getCommissionRate();
        $totalCommission = $totalRevenue * $commissionRate;
        $netProfit = $totalRevenue - $totalCommission;

        $couponQuery = Coupon::whereHas('offer', fn ($q) => $q->where('merchant_id', $merchant->id));
        
        $totalCouponsActivated = (clone $couponQuery)
            ->whereIn('status', ['activated', 'used'])
            ->count();

        $activeCoupons = (clone $couponQuery)
            ->where('status', 'active')
            ->count();

        $expiredCoupons = (clone $couponQuery)
            ->where('status', 'expired')
            ->count();

        $readyCoupons = $this->getReadyCouponsCount($merchant, $couponQuery);

        $monthlyRevenue = $this->getMonthlyRevenue($merchant);
        $weeklyPerformance = $this->getWeeklyPerformance($merchant);
        $recentActivations = $this->getRecentActivations($merchant, $couponQuery);
        $bestCoupons = $this->getBestCoupons($merchant);

        $adStats = $this->getAdStats($merchant);
        $offerStats = $this->getOfferStats($merchant);

        return [
            'today_revenue' => round($todayRevenue, 2),
            'today_activations' => $this->getTodayActivations($merchant, $couponQuery),
            'weekly_revenue' => round($weeklyRevenue, 2),
            'weekly_activations' => $this->getWeeklyActivations($merchant, $couponQuery),
            'average_order_value' => round($averageOrderValue, 2),
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'total_commission' => round($totalCommission, 2),
            'net_profit' => round($netProfit, 2),
            'ready_coupons' => $readyCoupons,
            'active_coupons' => $activeCoupons,
            'expired_coupons' => $expiredCoupons,
            'total_coupons_activated' => $totalCouponsActivated,
            'total_coupons_created' => (clone $couponQuery)->count(),
            'monthly_revenue' => $monthlyRevenue,
            'weekly_performance' => $weeklyPerformance,
            'recent_activations' => $recentActivations,
            'best_coupons' => $bestCoupons,
            'ad_under_review' => $adStats['under_review'],
            'ad_approved' => $adStats['approved'],
            'ad_rejected' => $adStats['rejected'],
            'weekly_views' => $offerStats['weekly_views'],
            'total_offers' => $offerStats['total'],
            'active_offers' => $offerStats['active'],
            'pending_offers' => $offerStats['pending'],
        ];
    }

    private function getTodayActivations(Merchant $merchant, $couponQuery): int
    {
        $today = now()->startOfDay();
        $query = (clone $couponQuery)->whereIn('status', ['activated', 'used'])
            ->where('updated_at', '>=', $today);
        
        if (\Schema::hasColumn('coupons', 'activated_at')) {
            return (clone $query)->where('activated_at', '>=', $today)->count();
        }
        
        return $query->count();
    }

    private function getWeeklyActivations(Merchant $merchant, $couponQuery): int
    {
        $weekStart = now()->startOfWeek();
        $query = (clone $couponQuery)->whereIn('status', ['activated', 'used'])
            ->where('updated_at', '>=', $weekStart);
        
        if (\Schema::hasColumn('coupons', 'activated_at')) {
            return (clone $query)->where('activated_at', '>=', $weekStart)->count();
        }
        
        return $query->count();
    }

    private function getReadyCouponsCount(Merchant $merchant, $couponQuery): int
    {
        if (\Schema::hasColumn('coupons', 'created_by') && \Schema::hasColumn('coupons', 'created_by_type')) {
            return Coupon::where('created_by', $merchant->id)
                ->where('created_by_type', 'merchant')
                ->where('status', 'paid')
                ->count();
        }
        
        return (clone $couponQuery)->where('status', 'active')->count();
    }

    private function getMonthlyRevenue(Merchant $merchant): array
    {
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStartDate = now()->subMonths($i)->startOfMonth();
            $monthEndDate = now()->subMonths($i)->endOfMonth();
            
            $monthRevenue = Order::where('merchant_id', $merchant->id)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$monthStartDate, $monthEndDate])
                ->sum('total_amount');
            
            $monthlyRevenue[] = round($monthRevenue, 2);
        }
        
        return $monthlyRevenue;
    }

    private function getWeeklyPerformance(Merchant $merchant): array
    {
        $weeklyPerformance = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();
            
            $dayRevenue = Order::where('merchant_id', $merchant->id)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('total_amount');
            
            $weeklyPerformance[] = round($dayRevenue, 2);
        }
        
        return $weeklyPerformance;
    }

    private function getRecentActivations(Merchant $merchant, $couponQuery): array
    {
        $query = (clone $couponQuery)
            ->whereIn('status', ['activated', 'used'])
            ->with('offer')
            ->orderBy('updated_at', 'desc')
            ->limit(5);

        if (\Schema::hasColumn('coupons', 'activated_at')) {
            $query->orderBy('activated_at', 'desc');
        }

        return $query->get()->map(function ($coupon) {
            $title = $coupon->offer->title ?? $coupon->offer->title_ar ?? $coupon->offer->title_en ?? 'N/A';
            $time = null;
            if (\Schema::hasColumn('coupons', 'activated_at') && $coupon->activated_at) {
                $time = $coupon->activated_at->format('H:i');
            }
            return [
                'id' => $coupon->id,
                'customer' => 'N/A',
                'coupon' => $title,
                'time' => $time ?? 'N/A',
            ];
        })->toArray();
    }

    private function getBestCoupons(Merchant $merchant): array
    {
        $commissionRate = FeatureFlagService::getCommissionRate();
        
        return Offer::where('merchant_id', $merchant->id)
            ->withCount(['coupons' => fn ($q) => $q->whereIn('status', ['activated', 'used'])])
            ->orderBy('coupons_count', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($offer) use ($merchant, $commissionRate) {
                $totalSales = Order::where('merchant_id', $merchant->id)
                    ->where('payment_status', 'paid')
                    ->sum('total_amount');
                $commission = $totalSales * $commissionRate;
                $profit = $totalSales - $commission;
                $title = $offer->title ?? $offer->title_ar ?? $offer->title_en ?? 'N/A';
                
                return [
                    'id' => $offer->id,
                    'title_ar' => $title,
                    'title_en' => $title,
                    'views' => 0,
                    'bookings' => $offer->coupons_count,
                    'sales' => round($totalSales, 2),
                    'commission' => round($commission, 2),
                    'profit' => round($profit, 2),
                ];
            })->toArray();
    }

    private function getAdStats(Merchant $merchant): array
    {
        return [
            'under_review' => $merchant->offers()->where('status', 'pending')->count(),
            'approved' => $merchant->offers()->where('status', 'active')->count(),
            'rejected' => $merchant->offers()->where('status', 'rejected')->count(),
        ];
    }

    private function getOfferStats(Merchant $merchant): array
    {
        $weeklyViews = 0;
        if (\Schema::hasColumn('offers', 'total_coupons')) {
            $weeklyViews = $merchant->offers()->sum('total_coupons') ?? 0;
        }
        
        return [
            'weekly_views' => $weeklyViews,
            'total' => $merchant->offers()->count(),
            'active' => $merchant->offers()->where('status', 'active')->count(),
            'pending' => $merchant->offers()->where('status', 'pending')->count(),
        ];
    }
}
