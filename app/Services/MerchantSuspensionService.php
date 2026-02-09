<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MerchantSuspensionService
{
    /**
     * Suspend merchant
     */
    public function suspend(Merchant $merchant, ?User $admin, ?\DateTime $until = null, string $reason = null, bool $freezeWallet = false): void
    {
        DB::beginTransaction();
        try {
            $merchant->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspended_until' => $until,
                'suspension_reason' => $reason,
                'suspended_by_admin_id' => $admin?->id,
            ]);

            // Hide all active offers
            Offer::where('merchant_id', $merchant->id)
                ->where('status', 'active')
                ->update(['status' => 'pending']); // Or create a 'hidden' status

            // Freeze wallet if requested
            if ($freezeWallet) {
                $walletService = app(WalletService::class);
                $walletService->freezeWallet($merchant, $admin);
            }

            // Invalidate API tokens (optional)
            // $merchant->user->tokens()->delete();

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $admin?->id,
                'merchant_suspended',
                Merchant::class,
                $merchant->id,
                "Merchant {$merchant->id} suspended. Reason: {$reason}",
                ['status' => 'active'],
                ['status' => 'suspended'],
                ['until' => $until?->toIso8601String(), 'freeze_wallet' => $freezeWallet]
            );

            DB::commit();

            // Send notification
            // TODO: Dispatch notification
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Merchant suspension failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Unfreeze/Re-enable merchant
     */
    public function unfreeze(Merchant $merchant, ?User $admin, string $reason = null): void
    {
        DB::beginTransaction();
        try {
            $merchant->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspended_until' => null,
                'suspension_reason' => null,
            ]);

            // Unfreeze wallet if frozen
            $walletService = app(WalletService::class);
            $walletService->unfreezeWallet($merchant, $admin);

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $admin?->id,
                'merchant_unfrozen',
                Merchant::class,
                $merchant->id,
                "Merchant {$merchant->id} unfrozen. Reason: {$reason}",
                ['status' => 'suspended'],
                ['status' => 'active'],
                ['reason' => $reason]
            );

            DB::commit();

            // Send notification
            // TODO: Dispatch notification
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Merchant unfreeze failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Disable merchant permanently
     */
    public function disable(Merchant $merchant, ?User $admin, string $reason = null): void
    {
        DB::beginTransaction();
        try {
            $merchant->update([
                'status' => 'disabled',
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'suspended_by_admin_id' => $admin?->id,
            ]);

            // Hide all offers
            Offer::where('merchant_id', $merchant->id)->update(['status' => 'expired']);

            // Freeze wallet
            $walletService = app(WalletService::class);
            $walletService->freezeWallet($merchant, $admin);

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $admin?->id,
                'merchant_disabled',
                Merchant::class,
                $merchant->id,
                "Merchant {$merchant->id} disabled. Reason: {$reason}",
                ['status' => $merchant->getOriginal('status')],
                ['status' => 'disabled'],
                ['reason' => $reason]
            );

            DB::commit();

            // Send notification
            // TODO: Dispatch notification
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Merchant disable failed: ' . $e->getMessage());
            throw $e;
        }
    }
}


