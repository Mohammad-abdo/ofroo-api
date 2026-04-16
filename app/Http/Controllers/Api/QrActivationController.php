<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesMerchantPortal;
use App\Http\Resources\CouponEntitlementResource;
use App\Models\ActivationReport;
use App\Services\QrActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrActivationController extends Controller
{
    use ResolvesMerchantPortal;

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
            'location_id' => 'nullable|integer',
        ]);
        
        // Use qr_code if provided, otherwise use coupon_code
        $code = $request->qr_code ?? $request->coupon_code;

        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

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

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
                'data' => $result['coupon'] ?? null,
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'coupon' => $result['coupon'] ?? null,
                'entitlement' => isset($result['entitlement'])
                    ? new CouponEntitlementResource($result['entitlement'])
                    : null,
                'redeem_type' => $result['redeem_type'] ?? null,
            ],
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
        
        $code = $request->qr_code ?? $request->coupon_code;

        $user = $request->user();
        $merchant = $this->resolveMerchant($request);

        $result = $this->qrService->validateQrCode($code, $merchant);

        if (!($result['valid'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Invalid code'], 400);
        }

        return response()->json([
            'valid' => true,
            'data' => $result['coupon'] ?? null,
            'can_activate' => $result['can_activate'] ?? false,
            'status' => $result['status'] ?? null,
            'redeem_type' => $result['redeem_type'] ?? null,
        ]);
    }

    /**
     * Get QR scanner page data
     */
    public function scannerPage(Request $request): JsonResponse
    {
        $merchant = $this->resolveMerchant($request);

        $recent = ActivationReport::where('merchant_id', $merchant->id)
            ->with(['coupon.offer'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (ActivationReport $row) {
                $c = $row->coupon;

                return [
                    'id' => $row->id,
                    'created_at' => $row->created_at?->toIso8601String(),
                    'activation_method' => $row->activation_method,
                    'coupon_code' => $c?->coupon_code ?? $c?->barcode,
                    'coupon_title' => $c && $c->offer
                        ? ($c->offer->title_ar ?? $c->offer->title_en ?? $c->offer->title)
                        : null,
                ];
            });

        return response()->json([
            'data' => [
                'recent_activations' => $recent,
            ],
        ]);
    }
}
