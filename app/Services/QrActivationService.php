<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\ActivationReport;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrActivationService
{
    private function findCouponByCode(string $couponCode): ?Coupon
    {
        return Coupon::with('offer')
            ->where(function ($q) use ($couponCode) {
                $q->where('coupon_code', $couponCode)->orWhere('barcode', $couponCode);
            })
            ->first();
    }

    public function activateCoupon(string $couponCode, Merchant $merchant, ?User $activatedBy, array $metadata = []): array
    {
        $coupon = $this->findCouponByCode($couponCode);

        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Coupon not found',
            ];
        }

        $offerMerchantId = $coupon->offer->merchant_id ?? null;
        if ($offerMerchantId && (int) $offerMerchantId !== (int) $merchant->id) {
            return [
                'success' => false,
                'message' => 'This coupon belongs to another merchant. You can only activate coupons from your own offers.',
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
            ];
        }

        if (!$coupon->offer) {
            return [
                'success' => false,
                'message' => 'Coupon has no linked offer',
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
            ];
        }

        if ($coupon->status === 'used') {
            return [
                'success' => false,
                'message' => 'Coupon already used',
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
            ];
        }

        if ($coupon->status === 'activated') {
            return [
                'success' => false,
                'message' => 'Coupon already activated',
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
            ];
        }

        if (in_array($coupon->status, ['cancelled', 'expired', 'inactive'])) {
            return [
                'success' => false,
                'message' => 'Coupon is ' . $coupon->status,
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
            ];
        }

        $activatableStatuses = ['reserved', 'paid', 'active', 'pending'];
        if (!in_array($coupon->status, $activatableStatuses)) {
            return [
                'success' => false,
                'message' => 'Coupon cannot be activated with current status: ' . $coupon->status,
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
            ];
        }

        DB::beginTransaction();
        try {
            $updateData = ['status' => 'used'];
            if ($coupon->usage_limit && $coupon->usage_limit > 0) {
                $updateData['times_used'] = ($coupon->times_used ?? 0) + 1;
            }
            $coupon->update($updateData);

            ActivationReport::create([
                'coupon_id' => $coupon->id,
                'merchant_id' => $merchant->id,
                'user_id' => $coupon->user_id ?? null,
                'order_id' => $coupon->order_id ?? null,
                'activation_method' => 'qr_scan',
                'device_id' => $metadata['device_id'] ?? null,
                'ip_address' => $metadata['ip_address'] ?? null,
                'location' => $metadata['location'] ?? null,
                'latitude' => $metadata['latitude'] ?? null,
                'longitude' => $metadata['longitude'] ?? null,
                'notes' => $metadata['notes'] ?? null,
            ]);

            try {
                $activityLogService = app(ActivityLogService::class);
                $activityLogService->log(
                    $activatedBy ? $activatedBy->id : null,
                    'coupon_activated',
                    Coupon::class,
                    $coupon->id,
                    "Coupon {$couponCode} activated by merchant {$merchant->id}",
                    null,
                    ['status' => 'used'],
                    $metadata
                );
            } catch (\Exception $e) {
                Log::warning('Activity log failed during QR activation: ' . $e->getMessage());
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Coupon activated successfully',
                'coupon' => new \App\Http\Resources\CouponResource($coupon->fresh()->load('offer')),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('QR Activation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Activation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function validateQrCode(string $couponCode, Merchant $merchant): array
    {
        $coupon = $this->findCouponByCode($couponCode);

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Coupon not found',
            ];
        }

        $offerMerchantId = $coupon->offer->merchant_id ?? null;
        if ($offerMerchantId && (int) $offerMerchantId !== (int) $merchant->id) {
            return [
                'valid' => false,
                'message' => 'This coupon belongs to another merchant. You can only scan coupons from your own offers.',
            ];
        }

        $activatable = ['reserved', 'paid', 'active', 'pending'];

        return [
            'valid' => true,
            'coupon' => new \App\Http\Resources\CouponResource($coupon),
            'can_activate' => in_array($coupon->status, $activatable),
            'status' => $coupon->status,
        ];
    }
}
