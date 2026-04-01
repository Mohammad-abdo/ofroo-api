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
    /**
     * Activate coupon by QR code scan
     */
    public function activateCoupon(string $couponCode, Merchant $merchant, ?User $activatedBy, array $metadata = []): array
    {
        $coupon = Coupon::with('offer')
            ->where('coupon_code', $couponCode)
            ->orWhere('barcode', $couponCode)
            ->first();

        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Coupon not found',
            ];
        }

        if ($coupon->offer && $coupon->offer->merchant_id !== $merchant->id) {
            return [
                'success' => false,
                'message' => 'Coupon does not belong to this merchant',
            ];
        }

        if ($coupon->status === 'activated') {
            return [
                'success' => false,
                'message' => 'Coupon already activated',
                'coupon' => $coupon,
            ];
        }

        if ($coupon->status === 'used') {
            return [
                'success' => false,
                'message' => 'Coupon already used',
            ];
        }

        if ($coupon->status === 'cancelled' || $coupon->status === 'expired' || $coupon->status === 'inactive') {
            return [
                'success' => false,
                'message' => 'Coupon is ' . $coupon->status,
            ];
        }

        $activatableStatuses = ['reserved', 'paid', 'active', 'pending'];
        if (!in_array($coupon->status, $activatableStatuses)) {
            return [
                'success' => false,
                'message' => 'Coupon cannot be activated with current status: ' . $coupon->status,
                'current_status' => $coupon->status,
            ];
        }

        DB::beginTransaction();
        try {
            $updateData = ['status' => 'used'];
            if ($coupon->usage_limit && $coupon->usage_limit > 0) {
                $updateData['times_used'] = ($coupon->times_used ?? 0) + 1;
            }
            $coupon->update($updateData);

            // Create activation report
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

            // TODO: Send notifications to user and merchant
            // dispatch(new SendCouponActivatedNotification($coupon));

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

    /**
     * Validate QR code without activating
     */
    public function validateQrCode(string $couponCode, Merchant $merchant): array
    {
        $coupon = Coupon::with('offer')
            ->where('coupon_code', $couponCode)
            ->orWhere('barcode', $couponCode)
            ->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Coupon not found',
            ];
        }

        if ($coupon->offer && $coupon->offer->merchant_id !== $merchant->id) {
            return [
                'valid' => false,
                'message' => 'Coupon does not belong to this merchant',
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


