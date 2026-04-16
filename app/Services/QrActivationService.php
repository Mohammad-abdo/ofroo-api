<?php

namespace App\Services;

use App\Models\ActivationReport;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\CouponEntitlementShare;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrActivationService
{
    public function activateCoupon(string $scanPayload, Merchant $merchant, ?User $activatedBy, array $metadata = []): array
    {
        $code = trim($scanPayload);
        if ($code === '') {
            return ['success' => false, 'message' => 'Empty scan payload'];
        }

        $share = CouponEntitlementShare::where('token', $code)->first();
        if ($share) {
            return $this->redeemShare($share, $merchant, $activatedBy, $metadata);
        }

        $entitlement = CouponEntitlement::where('redeem_token', $code)->first();
        if ($entitlement) {
            return $this->redeemWallet($entitlement, $merchant, $activatedBy, $metadata);
        }

        return [
            'success' => false,
            'message' => 'Invalid or unknown redemption token. Use wallet or share QR from the app.',
        ];
    }

    protected function redeemShare(
        CouponEntitlementShare $share,
        Merchant $merchant,
        ?User $activatedBy,
        array $metadata
    ): array {
        try {
            return DB::transaction(function () use ($share, $merchant, $activatedBy, $metadata) {
                $share = CouponEntitlementShare::where('id', $share->id)->lockForUpdate()->first();
                if (! $share || $share->status !== 'pending') {
                    return ['success' => false, 'message' => 'This share link has already been used or is invalid'];
                }

                if ($share->expires_at && $share->expires_at->isPast()) {
                    return ['success' => false, 'message' => 'This share link has expired'];
                }

                $parent = CouponEntitlement::where('id', $share->parent_entitlement_id)->lockForUpdate()->first();
                if (! $parent) {
                    return ['success' => false, 'message' => 'Entitlement not found'];
                }

                if ($parent->status !== 'active') {
                    return ['success' => false, 'message' => 'Coupon entitlement is not active'];
                }

                if ((int) $parent->reserved_shares_count < 1) {
                    return ['success' => false, 'message' => 'Share reservation is inconsistent'];
                }

                $coupon = Coupon::with('offer')->where('id', $parent->coupon_id)->first();
                if (! $coupon || ! $coupon->offer) {
                    return ['success' => false, 'message' => 'Coupon has no linked offer'];
                }

                $offerMerchantId = $coupon->offer->merchant_id ?? null;
                if ($offerMerchantId && (int) $offerMerchantId !== (int) $merchant->id) {
                    return [
                        'success' => false,
                        'message' => 'This coupon belongs to another merchant.',
                        'coupon' => new \App\Http\Resources\CouponResource($coupon),
                    ];
                }

                if ($this->templateNotRedeemable($coupon)) {
                    return [
                        'success' => false,
                        'message' => 'Coupon template is not valid for redemption',
                        'coupon' => new \App\Http\Resources\CouponResource($coupon),
                    ];
                }

                $parent->reserved_shares_count = (int) $parent->reserved_shares_count - 1;
                $parent->times_used = (int) $parent->times_used + 1;
                if ($parent->times_used >= (int) $parent->usage_limit) {
                    $parent->status = 'exhausted';
                }
                $parent->save();

                $share->update([
                    'status' => 'used',
                    'used_at' => now(),
                ]);

                $this->writeActivationReport(
                    $coupon,
                    $parent,
                    $share,
                    $merchant,
                    $activatedBy,
                    $metadata
                );

                $this->logActivity($activatedBy, $coupon, $parent, 'share', $metadata);

                return [
                    'success' => true,
                    'message' => 'Coupon activated successfully',
                    'coupon' => new \App\Http\Resources\CouponResource($coupon->fresh()->load('offer')),
                    'entitlement' => $parent->fresh(),
                    'redeem_type' => 'share',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Share redemption failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Activation failed: ' . $e->getMessage()];
        }
    }

    protected function redeemWallet(
        CouponEntitlement $entitlement,
        Merchant $merchant,
        ?User $activatedBy,
        array $metadata
    ): array {
        try {
            return DB::transaction(function () use ($entitlement, $merchant, $activatedBy, $metadata) {
                $entitlement = CouponEntitlement::where('id', $entitlement->id)->lockForUpdate()->first();
                if (! $entitlement) {
                    return ['success' => false, 'message' => 'Entitlement not found'];
                }

                if ($entitlement->status !== 'active') {
                    return ['success' => false, 'message' => 'Coupon is not active yet or is no longer valid'];
                }

                if ($entitlement->remainingUses() < 1) {
                    return ['success' => false, 'message' => 'No remaining uses on this coupon'];
                }

                $coupon = Coupon::with('offer')->where('id', $entitlement->coupon_id)->first();
                if (! $coupon || ! $coupon->offer) {
                    return ['success' => false, 'message' => 'Coupon has no linked offer'];
                }

                $offerMerchantId = $coupon->offer->merchant_id ?? null;
                if ($offerMerchantId && (int) $offerMerchantId !== (int) $merchant->id) {
                    return [
                        'success' => false,
                        'message' => 'This coupon belongs to another merchant.',
                        'coupon' => new \App\Http\Resources\CouponResource($coupon),
                    ];
                }

                if ($this->templateNotRedeemable($coupon)) {
                    return [
                        'success' => false,
                        'message' => 'Coupon template is not valid for redemption',
                        'coupon' => new \App\Http\Resources\CouponResource($coupon),
                    ];
                }

                $entitlement->times_used = (int) $entitlement->times_used + 1;
                if ($entitlement->times_used >= (int) $entitlement->usage_limit) {
                    $entitlement->status = 'exhausted';
                }
                $entitlement->save();

                $this->writeActivationReport(
                    $coupon,
                    $entitlement,
                    null,
                    $merchant,
                    $activatedBy,
                    $metadata
                );

                $this->logActivity($activatedBy, $coupon, $entitlement, 'wallet', $metadata);

                return [
                    'success' => true,
                    'message' => 'Coupon activated successfully',
                    'coupon' => new \App\Http\Resources\CouponResource($coupon->fresh()->load('offer')),
                    'entitlement' => $entitlement->fresh(),
                    'redeem_type' => 'wallet',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Wallet redemption failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Activation failed: ' . $e->getMessage()];
        }
    }

    protected function templateNotRedeemable(Coupon $coupon): bool
    {
        if ($coupon->isExpired()) {
            return true;
        }
        if ($coupon->isNotYetStarted()) {
            return true;
        }
        $st = strtolower((string) ($coupon->status ?? ''));
        if (in_array($st, ['cancelled', 'expired', 'inactive'], true)) {
            return true;
        }

        return false;
    }

    protected function writeActivationReport(
        Coupon $coupon,
        CouponEntitlement $entitlement,
        ?CouponEntitlementShare $share,
        Merchant $merchant,
        ?User $activatedBy,
        array $metadata
    ): void {
        ActivationReport::create([
            'coupon_id' => $coupon->id,
            'coupon_entitlement_id' => $entitlement->id,
            'coupon_entitlement_share_id' => $share?->id,
            'merchant_id' => $merchant->id,
            'user_id' => $entitlement->user_id,
            'activated_by_user_id' => $activatedBy?->id,
            'order_id' => $entitlement->order_id,
            'activation_method' => $metadata['activation_method'] ?? 'qr_scan',
            'device_id' => $metadata['device_id'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'location' => $metadata['location'] ?? null,
            'latitude' => $metadata['latitude'] ?? null,
            'longitude' => $metadata['longitude'] ?? null,
            'notes' => $metadata['notes'] ?? null,
        ]);
    }

    protected function logActivity(
        ?User $activatedBy,
        Coupon $coupon,
        CouponEntitlement $entitlement,
        string $type,
        array $metadata
    ): void {
        try {
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $activatedBy ? $activatedBy->id : null,
                'coupon_activated',
                Coupon::class,
                $coupon->id,
                "Coupon {$coupon->id} redeemed ({$type}) entitlement {$entitlement->id}",
                null,
                ['entitlement_id' => $entitlement->id],
                $metadata
            );
        } catch (\Exception $e) {
            Log::warning('Activity log failed during QR activation: ' . $e->getMessage());
        }
    }

    public function validateQrCode(string $scanPayload, Merchant $merchant): array
    {
        $code = trim($scanPayload);
        if ($code === '') {
            return ['valid' => false, 'message' => 'Empty scan payload'];
        }

        $share = CouponEntitlementShare::where('token', $code)->first();
        if ($share) {
            if ($share->status !== 'pending') {
                return ['valid' => false, 'message' => 'Share already used'];
            }
            if ($share->expires_at && $share->expires_at->isPast()) {
                return ['valid' => false, 'message' => 'Share expired'];
            }
            $parent = CouponEntitlement::with(['coupon.offer'])->find($share->parent_entitlement_id);
            if (! $parent || ! $parent->coupon?->offer) {
                return ['valid' => false, 'message' => 'Invalid share'];
            }
            if ((int) $parent->coupon->offer->merchant_id !== (int) $merchant->id) {
                return ['valid' => false, 'message' => 'Wrong merchant'];
            }
            if ($this->templateNotRedeemable($parent->coupon)) {
                return ['valid' => false, 'message' => 'Coupon template not valid'];
            }

            return [
                'valid' => true,
                'coupon' => new \App\Http\Resources\CouponResource($parent->coupon),
                'can_activate' => true,
                'status' => $share->status,
                'redeem_type' => 'share',
            ];
        }

        $entitlement = CouponEntitlement::with(['coupon.offer'])->where('redeem_token', $code)->first();
        if ($entitlement) {
            if ($entitlement->status !== 'active') {
                return ['valid' => false, 'message' => 'Entitlement not active', 'can_activate' => false];
            }
            if ($entitlement->remainingUses() < 1) {
                return ['valid' => true, 'message' => 'No uses left', 'can_activate' => false];
            }
            $coupon = $entitlement->coupon;
            if (! $coupon?->offer) {
                return ['valid' => false, 'message' => 'Invalid coupon'];
            }
            if ((int) $coupon->offer->merchant_id !== (int) $merchant->id) {
                return ['valid' => false, 'message' => 'Wrong merchant'];
            }
            if ($this->templateNotRedeemable($coupon)) {
                return ['valid' => false, 'message' => 'Coupon template not valid'];
            }

            return [
                'valid' => true,
                'coupon' => new \App\Http\Resources\CouponResource($coupon),
                'can_activate' => true,
                'status' => $entitlement->status,
                'redeem_type' => 'wallet',
            ];
        }

        return ['valid' => false, 'message' => 'Unknown token'];
    }

    /**
     * Manual activation by entitlement id (merchant portal).
     */
    public function activateEntitlementById(int $entitlementId, Merchant $merchant, ?User $activatedBy, array $metadata = []): array
    {
        $entitlement = CouponEntitlement::with(['coupon.offer'])->find($entitlementId);
        if (! $entitlement || ! $entitlement->coupon?->offer) {
            return ['success' => false, 'message' => 'Entitlement not found'];
        }
        if ((int) $entitlement->coupon->offer->merchant_id !== (int) $merchant->id) {
            return ['success' => false, 'message' => 'Not allowed for this merchant'];
        }

        return $this->redeemWallet($entitlement, $merchant, $activatedBy, array_merge($metadata, ['activation_method' => 'manual']));
    }
}
