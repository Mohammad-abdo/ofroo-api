<?php

namespace App\Services;

use App\Http\Resources\CouponResource;
use App\Models\ActivationReport;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\CouponEntitlementShare;
use App\Models\Merchant;
use App\Models\Order;
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

        if (str_starts_with($code, '{')) {
            $decoded = json_decode($code, true);
            if (is_array($decoded) && isset($decoded['token'])) {
                $extracted = trim((string) $decoded['token']);
                if ($extracted !== '') {
                    $code = $extracted;
                }
            }
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

                // Lock the parent order (if any) so we can finalize the
                // reservation lifecycle alongside the share redemption.
                $parentOrder = $parent->order_id
                    ? Order::with('items.offer')
                        ->where('id', $parent->order_id)
                        ->lockForUpdate()
                        ->first()
                    : null;

                if ($parentOrder && $parentOrder->status === 'expired') {
                    return ['success' => false, 'message' => 'Reservation has expired'];
                }

                $parentActivatableViaOrder = $parent->status === 'pending'
                    && $parentOrder
                    && $parentOrder->status === 'pending';

                if ($parent->status !== 'active' && ! $parentActivatableViaOrder) {
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
                        'coupon' => new CouponResource($coupon),
                    ];
                }

                if ($this->templateNotRedeemable($coupon)) {
                    return [
                        'success' => false,
                        'message' => 'Coupon template is not valid for redemption',
                        'coupon' => new CouponResource($coupon),
                    ];
                }

                // Finalize the parent order's reservation on the first scan
                // (no-op on subsequent scans). See redeemWallet() for details.
                if ($parentOrder) {
                    $this->finalizeOrderActivation($parentOrder);
                    $parent = $parent->fresh();
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
                    'coupon' => new CouponResource($coupon->fresh()->load('offer')),
                    'entitlement' => $parent->fresh(),
                    'redeem_type' => 'share',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Share redemption failed: '.$e->getMessage());

            return ['success' => false, 'message' => 'Activation failed: '.$e->getMessage()];
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

                // Lock the parent order (if any) so we can safely finalize the
                // reservation lifecycle alongside the entitlement update.
                $order = $entitlement->order_id
                    ? Order::with('items.offer')
                        ->where('id', $entitlement->order_id)
                        ->lockForUpdate()
                        ->first()
                    : null;

                if ($order && $order->status === 'expired') {
                    return ['success' => false, 'message' => 'Reservation has expired'];
                }

                // Allow scanning when the entitlement is active OR when it is
                // still pending and its order is in the pending reservation
                // state (the cash flow). The pending entitlement is flipped to
                // active by finalizeOrderActivation() below.
                $isActivatableViaOrder = $entitlement->status === 'pending'
                    && $order
                    && $order->status === 'pending';

                if ($entitlement->status !== 'active' && ! $isActivatableViaOrder) {
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
                        'coupon' => new CouponResource($coupon),
                    ];
                }

                if ($this->templateNotRedeemable($coupon)) {
                    return [
                        'success' => false,
                        'message' => 'Coupon template is not valid for redemption',
                        'coupon' => new CouponResource($coupon),
                    ];
                }

                // Finalize the reservation lifecycle on the first scan of this
                // order: move reserved -> used (NOT touching coupons_remaining),
                // run idempotent wallet credit + commission, and flip the order
                // to 'activated' / payment_status 'paid'. Subsequent scans of
                // the same order short-circuit safely inside the helper.
                if ($order) {
                    $this->finalizeOrderActivation($order);
                    // Refresh the entitlement so the status update applied by
                    // finalizeOrderActivation (pending -> active) is visible.
                    $entitlement = $entitlement->fresh();
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
                    'coupon' => new CouponResource($coupon->fresh()->load('offer')),
                    'entitlement' => $entitlement->fresh(),
                    'redeem_type' => 'wallet',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Wallet redemption failed: '.$e->getMessage());

            return ['success' => false, 'message' => 'Activation failed: '.$e->getMessage()];
        }
    }

    /**
     * Finalize the reservation lifecycle for an order at first QR scan.
     *
     * This method is the single source of truth for the transitions that
     * used to happen at checkout in the legacy purchase-based flow:
     *
     *   - Inventory: reserved_quantity -= qty, used_quantity += qty
     *     (coupons_remaining is NOT touched here — it was already decremented
     *      at checkout, and the rule is mobile-compat: coupons_remaining
     *      represents "no longer available", which is true for both reserved
     *      and used quantities.)
     *
     *   - Wallet credit + commission: idempotent. Runs at most once per order
     *     based on (wallet_processed_at IS NULL && Commission row missing).
     *
     *   - Order metadata: status='activated', activated_at=now(), and
     *     payment_status='paid' if it wasn't already (cash flow).
     *
     *   - Pending entitlements (cash flow) are flipped to 'active' so the
     *     calling redeem method can safely consume a use.
     *
     * Caller must hold a row-level lock on $order and run inside DB::transaction.
     */
    protected function finalizeOrderActivation(Order $order): void
    {
        // Re-load under row lock so concurrent scanners / jobs see one serialized
        // view of wallet_processed_at + status (matches checkout post-commit path).
        $locked = Order::with('items.offer')
            ->whereKey($order->id)
            ->lockForUpdate()
            ->first();

        if (! $locked || $locked->status !== 'pending') {
            return;
        }

        foreach ($locked->items as $item) {
            if ($item->offer) {
                $item->offer->consumeReserved((int) $item->quantity);
            }
        }

        // Wallet + commission: second nested transaction + FOR UPDATE so two
        // racing workers cannot both pass the idempotency gate.
        DB::transaction(function () use ($locked) {
            $o = Order::whereKey($locked->id)->lockForUpdate()->first();
            if (! $o) {
                return;
            }
            if ($o->wallet_processed_at !== null) {
                return;
            }
            if (Commission::where('order_id', $o->id)->exists()) {
                return;
            }

            try {
                app(WalletService::class)->processOrderPayment($o);
                $o->forceFill(['wallet_processed_at' => now()])->save();
            } catch (\Throwable $walletEx) {
                Log::error('Order activation wallet processing failed', [
                    'order_id' => $o->id,
                    'error' => $walletEx->getMessage(),
                ]);
                throw $walletEx;
            }
        });

        $locked->refresh();
        $updates = [
            'status' => 'activated',
            'activated_at' => $locked->activated_at ?: now(),
        ];
        $wasPaidAlready = $locked->payment_status === 'paid';
        if (! $wasPaidAlready) {
            $updates['payment_status'] = 'paid';
        }
        $locked->forceFill($updates)->save();

        if (! $wasPaidAlready) {
            try {
                app(CouponService::class)->updateCouponStatusAfterPayment($locked);
            } catch (\Throwable $couponEx) {
                Log::warning('updateCouponStatusAfterPayment failed during QR activation', [
                    'order_id' => $locked->id,
                    'error' => $couponEx->getMessage(),
                ]);
            }
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
            Log::warning('Activity log failed during QR activation: '.$e->getMessage());
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
                'coupon' => new CouponResource($parent->coupon),
                'can_activate' => true,
                'status' => $share->status,
                'redeem_type' => 'share',
            ];
        }

        $entitlement = CouponEntitlement::with(['coupon.offer', 'order'])->where('redeem_token', $code)->first();
        if ($entitlement) {
            // Treat pending entitlements as valid-for-activation when their
            // order is still in the pending reservation window (cash flow).
            $order = $entitlement->order;
            $activatableViaOrder = $entitlement->status === 'pending'
                && $order
                && $order->status === 'pending';

            if ($entitlement->status !== 'active' && ! $activatableViaOrder) {
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
                'coupon' => new CouponResource($coupon),
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
