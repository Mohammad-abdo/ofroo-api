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
        $coupon = Coupon::where('coupon_code', $couponCode)
            ->orWhere('barcode_value', $couponCode)
            ->first();

        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Coupon not found',
            ];
        }

        // Validate coupon belongs to merchant
        if ($coupon->offer->merchant_id !== $merchant->id) {
            return [
                'success' => false,
                'message' => 'Coupon does not belong to this merchant',
            ];
        }

        // Check status
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

        if ($coupon->status === 'cancelled' || $coupon->status === 'expired') {
            return [
                'success' => false,
                'message' => 'Coupon is ' . $coupon->status,
            ];
        }

        // Only activate if status is 'reserved' or 'paid'
        if (!in_array($coupon->status, ['reserved', 'paid'])) {
            return [
                'success' => false,
                'message' => 'Coupon status must be reserved or paid to activate',
                'current_status' => $coupon->status,
            ];
        }

        DB::beginTransaction();
        try {
            // Update coupon
            $coupon->update([
                'status' => 'activated',
                'activated_at' => now(),
                'activated_by' => $activatedBy ? $activatedBy->id : null,
                'activation_device_id' => $metadata['device_id'] ?? null,
                'activation_ip' => $metadata['ip_address'] ?? null,
            ]);

            // Create activation report
            ActivationReport::create([
                'coupon_id' => $coupon->id,
                'merchant_id' => $merchant->id,
                'user_id' => $coupon->user_id,
                'order_id' => $coupon->order_id,
                'activation_method' => 'qr_scan',
                'device_id' => $metadata['device_id'] ?? null,
                'ip_address' => $metadata['ip_address'] ?? null,
                'location' => $metadata['location'] ?? null,
                'latitude' => $metadata['latitude'] ?? null,
                'longitude' => $metadata['longitude'] ?? null,
                'notes' => $metadata['notes'] ?? null,
            ]);

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $activatedBy ? $activatedBy->id : null,
                'coupon_activated',
                Coupon::class,
                $coupon->id,
                "Coupon {$couponCode} activated by merchant {$merchant->id}",
                null,
                ['status' => 'activated'],
                $metadata
            );

            DB::commit();

            // TODO: Send notifications to user and merchant
            // dispatch(new SendCouponActivatedNotification($coupon));

            return [
                'success' => true,
                'message' => 'Coupon activated successfully',
                'coupon' => $coupon->fresh(),
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
        $coupon = Coupon::where('coupon_code', $couponCode)
            ->orWhere('barcode_value', $couponCode)
            ->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Coupon not found',
            ];
        }

        if ($coupon->offer->merchant_id !== $merchant->id) {
            return [
                'valid' => false,
                'message' => 'Coupon does not belong to this merchant',
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'can_activate' => in_array($coupon->status, ['reserved', 'paid']),
            'status' => $coupon->status,
        ];
    }
}


