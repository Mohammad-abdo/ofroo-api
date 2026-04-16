<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponEntitlementResource;
use App\Models\CouponEntitlement;
use App\Models\CouponEntitlementShare;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CouponEntitlementController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Create a one-time share token for a friend (consumes one reserved slot from the pool).
     */
    public function share(Request $request, int $entitlementId): JsonResponse
    {
        $request->validate([
            'expires_at' => 'nullable|date|after:now',
        ]);

        $user = $request->user();

        $entitlement = CouponEntitlement::with(['coupon.offer'])
            ->where('user_id', $user->id)
            ->findOrFail($entitlementId);

        if ($entitlement->status !== 'active') {
            return response()->json([
                'message' => 'Entitlement is not active',
            ], 400);
        }

        try {
            $share = DB::transaction(function () use ($entitlement, $request) {
                $row = CouponEntitlement::where('id', $entitlement->id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'active') {
                    throw new \RuntimeException('Entitlement is not active');
                }
                if ($row->remainingUses() < 1) {
                    throw new \RuntimeException('No remaining uses to share');
                }

                $row->reserved_shares_count = (int) $row->reserved_shares_count + 1;
                $row->save();

                return CouponEntitlementShare::create([
                    'parent_entitlement_id' => $row->id,
                    'token' => $this->couponService->generateShareToken(),
                    'status' => 'pending',
                    'expires_at' => $request->filled('expires_at') ? Carbon::parse($request->expires_at) : null,
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Could not create share: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Share created',
            'data' => [
                'token' => $share->token,
                'share_qr_value' => $share->token,
                'expires_at' => $share->expires_at?->toIso8601String(),
                'entitlement' => new CouponEntitlementResource($entitlement->fresh()->load('coupon.offer')),
            ],
        ], 201);
    }
}
