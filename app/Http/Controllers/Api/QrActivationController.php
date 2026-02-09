<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Services\QrActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrActivationController extends Controller
{
    protected QrActivationService $qrService;

    public function __construct(QrActivationService $qrService)
    {
        $this->qrService = $qrService;
    }

    /**
     * Scan and activate QR code
     */
    public function scanAndActivate(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code' => 'required_without:qr_code|string',
            'qr_code' => 'required_without:coupon_code|string',
            'device_id' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_id' => 'nullable|exists:store_locations,id',
        ]);
        
        // Use qr_code if provided, otherwise use coupon_code
        $code = $request->qr_code ?? $request->coupon_code;

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $result = $this->qrService->activateCoupon(
            $code,
            $merchant,
            $user,
            [
                'device_id' => $request->device_id,
                'ip_address' => $request->ip(),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'location' => $request->location,
                'location_id' => $request->location_id,
            ]
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'data' => $result['coupon'] ?? null,
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['coupon'],
        ]);
    }

    /**
     * Validate QR code without activating
     */
    public function validateQr(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code' => 'required_without:qr_code|string',
            'qr_code' => 'required_without:coupon_code|string',
        ]);
        
        // Use qr_code if provided, otherwise use coupon_code
        $code = $request->qr_code ?? $request->coupon_code;

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $result = $this->qrService->validateQrCode($code, $merchant);

        return response()->json($result);
    }

    /**
     * Get QR scanner page data
     */
    public function scannerPage(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        // Get pending/reserved coupons for this merchant
        $pendingCoupons = Coupon::whereHas('offer', function ($q) use ($merchant) {
            $q->where('merchant_id', $merchant->id);
        })
        ->whereIn('status', ['reserved', 'paid'])
        ->with(['user', 'offer'])
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();

        return response()->json([
            'data' => [
                'pending_count' => $pendingCoupons->count(),
                'recent_coupons' => $pendingCoupons,
            ],
        ]);
    }
}
