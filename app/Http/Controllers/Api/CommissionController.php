<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionResource;
use App\Services\CommissionService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService,
        protected WalletService $walletService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $result = $this->commissionService->getCommissionsByMerchant($perPage);

        return response()->json([
            'data' => CommissionResource::collection(collect($result['data'])),
            'meta' => $result['meta'],
        ]);
    }

    public function byMerchant(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $result = $this->commissionService->getCommissionsByMerchantGrouped($perPage);

        return response()->json($result);
    }

    public function summary(): JsonResponse
    {
        $summary = $this->commissionService->getCommissionsSummary();
        $monthly = $this->commissionService->getMonthlyCommission(6);

        return response()->json([
            'data' => [
                'summary' => $summary,
                'monthly' => $monthly,
            ],
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $filters = $request->only(['merchant_id', 'from_date', 'to_date']);
        $data = $this->commissionService->getCommissionsForExport($filters);

        return response()->json([
            'data' => $data,
            'total' => count($data),
        ]);
    }
}
