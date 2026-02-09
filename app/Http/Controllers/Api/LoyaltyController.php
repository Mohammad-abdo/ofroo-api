<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    protected LoyaltyService $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    /**
     * Get loyalty account
     */
    public function account(Request $request): JsonResponse
    {
        $user = $request->user();
        $loyaltyAccount = $this->loyaltyService->getOrCreateLoyaltyAccount($user);
        $benefits = $this->loyaltyService->getTierBenefits($loyaltyAccount->tier);

        return response()->json([
            'data' => [
                'total_points' => $loyaltyAccount->total_points,
                'tier' => $loyaltyAccount->tier,
                'points_used' => $loyaltyAccount->points_used,
                'points_expired' => $loyaltyAccount->points_expired,
                'available_points' => $loyaltyAccount->total_points - $loyaltyAccount->points_used,
                'benefits' => $benefits,
            ],
        ]);
    }

    /**
     * Get loyalty transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = LoyaltyTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Redeem points
     */
    public function redeem(Request $request): JsonResponse
    {
        $request->validate([
            'points' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $success = $this->loyaltyService->redeemPoints(
            $user,
            $request->points,
            $request->description
        );

        if (!$success) {
            return response()->json([
                'message' => 'Insufficient points',
            ], 400);
        }

        return response()->json([
            'message' => 'Points redeemed successfully',
            'data' => $this->loyaltyService->getOrCreateLoyaltyAccount($user),
        ]);
    }
}
