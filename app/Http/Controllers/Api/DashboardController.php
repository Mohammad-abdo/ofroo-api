<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function stats(): JsonResponse
    {
        $stats = $this->dashboardService->getStats();

        return response()->json([
            'data' => $stats,
        ]);
    }

    public function overview(): JsonResponse
    {
        $stats = $this->dashboardService->getStats();
        $recentOrders = $this->dashboardService->getRecentOrders();
        $topMerchants = $this->dashboardService->getTopMerchants();

        return response()->json([
            'data' => [
                'stats' => $stats,
                'recent_orders' => $recentOrders,
                'top_merchants' => $topMerchants,
            ],
        ]);
    }

    public function refresh(): JsonResponse
    {
        $this->dashboardService->clearCache();
        $stats = $this->dashboardService->getStats();

        return response()->json([
            'message' => 'Dashboard cache refreshed',
            'data' => $stats,
        ]);
    }
}
