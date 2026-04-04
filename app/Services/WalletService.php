<?php

namespace App\Services;

use App\Models\AdminWallet;
use App\Models\MerchantWallet;
use App\Models\WalletTransaction;
use App\Models\Order;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Credit merchant wallet
     */
    public function creditMerchantWallet(Merchant $merchant, float $amount, string $transactionType, $related = null, string $note = null, ?User $createdBy = null): WalletTransaction
    {
        DB::beginTransaction();
        try {
            $wallet = MerchantWallet::firstOrCreate(
                ['merchant_id' => $merchant->id],
                ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
            );

            if ($wallet->is_frozen) {
                throw new \Exception('Wallet is frozen');
            }

            $balanceBefore = $wallet->balance;
            $wallet->increment('balance', $amount);
            $balanceAfter = $wallet->balance;

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'wallet_type' => 'merchant',
                'transaction_type' => $transactionType,
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related ? $related->id : null,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
                'created_by_user_id' => $createdBy ? $createdBy->id : null,
            ]);

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $createdBy ? $createdBy->id : null,
                'wallet_credited',
                MerchantWallet::class,
                $wallet->id,
                "Merchant wallet credited with {$amount} EGP",
                ['balance' => $balanceBefore],
                ['balance' => $balanceAfter],
                ['transaction_id' => $transaction->id, 'merchant_id' => $merchant->id]
            );

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet credit failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Debit merchant wallet
     */
    public function debitMerchantWallet(Merchant $merchant, float $amount, string $transactionType, $related = null, string $note = null, ?User $createdBy = null): WalletTransaction
    {
        DB::beginTransaction();
        try {
            $wallet = MerchantWallet::firstOrCreate(
                ['merchant_id' => $merchant->id],
                ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
            );

            if ($wallet->is_frozen) {
                throw new \Exception('Wallet is frozen');
            }

            $availableBalance = $wallet->balance - $wallet->reserved_balance;
            if ($availableBalance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $balanceBefore = $wallet->balance;
            $wallet->decrement('balance', $amount);
            $balanceAfter = $wallet->balance;

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'wallet_type' => 'merchant',
                'transaction_type' => $transactionType,
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related ? $related->id : null,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
                'created_by_user_id' => $createdBy ? $createdBy->id : null,
            ]);

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $createdBy ? $createdBy->id : null,
                'wallet_debited',
                MerchantWallet::class,
                $wallet->id,
                "Merchant wallet debited {$amount} EGP",
                ['balance' => $balanceBefore],
                ['balance' => $balanceAfter],
                ['transaction_id' => $transaction->id, 'merchant_id' => $merchant->id]
            );

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet debit failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Credit admin wallet
     */
    public function creditAdminWallet(float $amount, string $transactionType, $related = null, string $note = null, ?User $createdBy = null): WalletTransaction
    {
        DB::beginTransaction();
        try {
            $wallet = AdminWallet::getOrCreate();

            $balanceBefore = $wallet->balance;
            $wallet->increment('balance', $amount);
            $balanceAfter = $wallet->balance;

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'wallet_type' => 'admin',
                'transaction_type' => $transactionType,
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related ? $related->id : null,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
                'created_by_user_id' => $createdBy ? $createdBy->id : null,
            ]);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin wallet credit failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Debit admin wallet
     */
    public function debitAdminWallet(float $amount, string $transactionType, $related = null, string $note = null, ?User $createdBy = null): WalletTransaction
    {
        DB::beginTransaction();
        try {
            $wallet = AdminWallet::getOrCreate();

            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient admin wallet balance');
            }

            $balanceBefore = $wallet->balance;
            $wallet->decrement('balance', $amount);
            $balanceAfter = $wallet->balance;

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'wallet_type' => 'admin',
                'transaction_type' => $transactionType,
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related ? $related->id : null,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
                'created_by_user_id' => $createdBy ? $createdBy->id : null,
            ]);

            DB::commit();
            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin wallet debit failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process order payment - credit merchant and admin wallets
     */
    public function processOrderPayment(Order $order): void
    {
        $merchant = $order->merchant;
        $commissionRate = \App\Services\CommissionRateResolver::effectiveDecimalRate($merchant);
        $commissionAmount = $order->total_amount * $commissionRate;
        $netAmount = $order->total_amount - $commissionAmount;

        // Credit merchant wallet with net amount
        $this->creditMerchantWallet(
            $merchant,
            $netAmount,
            'credit',
            $order,
            "Payment for Order #{$order->id}",
            $order->user
        );

        // Credit admin wallet with commission
        $this->creditAdminWallet(
            $commissionAmount,
            'commission',
            $order,
            "Commission from Order #{$order->id}",
            $order->user
        );

        // Update merchant wallet total_earned and total_commission_paid
        $wallet = MerchantWallet::where('merchant_id', $merchant->id)->first();
        if ($wallet) {
            $wallet->increment('total_earned', $netAmount);
            $wallet->increment('total_commission_paid', $commissionAmount);
        }

        // Create commission record
        \App\Models\Commission::create([
            'order_id' => $order->id,
            'merchant_id' => $merchant->id,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'status' => 'completed',
        ]);
    }

    /**
     * Refund order - reverse payment from wallets
     */
    public function refundToWallet(Order $order, ?User $initiatedBy = null): void
    {
        $merchant = $order->merchant;
        $commissionRow = \App\Models\Commission::where('order_id', $order->id)->first();
        if ($commissionRow) {
            $commissionAmount = (float) $commissionRow->commission_amount;
            $netAmount = (float) $order->total_amount - $commissionAmount;
        } else {
            $commissionRate = \App\Services\CommissionRateResolver::effectiveDecimalRate($merchant);
            $commissionAmount = (float) $order->total_amount * $commissionRate;
            $netAmount = (float) $order->total_amount - $commissionAmount;
        }

        // Debit merchant wallet (reverse the credit)
        $this->debitMerchantWallet(
            $merchant,
            $netAmount,
            'refund',
            $order,
            "Refund for Order #{$order->id}",
            $initiatedBy
        );

        // Debit admin wallet (reverse the commission)
        $this->debitAdminWallet(
            $commissionAmount,
            'refund',
            $order,
            "Commission refund for Order #{$order->id}",
            $initiatedBy
        );

        // Update merchant wallet totals
        $wallet = MerchantWallet::where('merchant_id', $merchant->id)->first();
        if ($wallet) {
            $wallet->decrement('total_earned', $netAmount);
            $wallet->decrement('total_commission_paid', $commissionAmount);
        }

        // Update commission record
        $commission = \App\Models\Commission::where('order_id', $order->id)->first();
        if ($commission) {
            $commission->update(['status' => 'refunded']);
        }
    }

    /**
     * Get minimum withdrawal amount
     */
    public function getMinimumWithdrawal(): float
    {
        return (float) \App\Models\Setting::getValue('minimum_withdrawal', 100);
    }

    /**
     * Get withdrawal fee
     */
    public function getWithdrawalFee(): float
    {
        return (float) \App\Models\Setting::getValue('withdrawal_fee', 0);
    }

    /**
     * Get withdrawal fee percentage
     */
    public function getWithdrawalFeePercent(): float
    {
        return (float) \App\Models\Setting::getValue('withdrawal_fee_percent', 0);
    }

    /**
     * Calculate withdrawal fee
     */
    public function calculateWithdrawalFee(float $amount): float
    {
        $fixedFee = $this->getWithdrawalFee();
        $percentFee = $this->getWithdrawalFeePercent();

        $fee = $fixedFee + ($amount * $percentFee / 100);

        return round($fee, 2);
    }

    /**
     * Check if withdrawal is allowed
     */
    public function canWithdraw(Merchant $merchant, float $amount): array
    {
        $wallet = MerchantWallet::where('merchant_id', $merchant->id)->first();

        if (!$wallet) {
            return ['allowed' => false, 'message' => 'Wallet not found'];
        }

        if ($wallet->is_frozen) {
            return ['allowed' => false, 'message' => 'Wallet is frozen'];
        }

        $availableBalance = $wallet->balance - $wallet->reserved_balance;

        if ($availableBalance < $amount) {
            return [
                'allowed' => false,
                'message' => 'Insufficient balance',
                'available' => $availableBalance,
            ];
        }

        $minimum = $this->getMinimumWithdrawal();
        if ($amount < $minimum) {
            return [
                'allowed' => false,
                'message' => "Minimum withdrawal is {$minimum} EGP",
                'minimum' => $minimum,
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Reserve balance (for pending/held funds)
     */
    public function reserveBalance(Merchant $merchant, float $amount, string $note = null): void
    {
        $wallet = MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
        );

        $availableBalance = $wallet->balance - $wallet->reserved_balance;
        if ($availableBalance < $amount) {
            throw new \Exception('Insufficient available balance');
        }

        $wallet->increment('reserved_balance', $amount);
    }

    /**
     * Release reserved balance
     */
    public function releaseReservedBalance(Merchant $merchant, float $amount): void
    {
        $wallet = MerchantWallet::where('merchant_id', $merchant->id)->firstOrFail();
        
        if ($wallet->reserved_balance < $amount) {
            throw new \Exception('Insufficient reserved balance');
        }

        $wallet->decrement('reserved_balance', $amount);
    }

    /**
     * Freeze merchant wallet
     */
    public function freezeWallet(Merchant $merchant, ?User $admin = null): void
    {
        $wallet = MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
        );

        $wallet->update(['is_frozen' => true]);

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin ? $admin->id : null,
            'wallet_frozen',
            MerchantWallet::class,
            $wallet->id,
            "Merchant wallet frozen",
            ['is_frozen' => false],
            ['is_frozen' => true],
            ['merchant_id' => $merchant->id]
        );
    }

    /**
     * Unfreeze merchant wallet
     */
    public function unfreezeWallet(Merchant $merchant, ?User $admin = null): void
    {
        $wallet = MerchantWallet::where('merchant_id', $merchant->id)->firstOrFail();
        $wallet->update(['is_frozen' => false]);

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin ? $admin->id : null,
            'wallet_unfrozen',
            MerchantWallet::class,
            $wallet->id,
            "Merchant wallet unfrozen",
            ['is_frozen' => true],
            ['is_frozen' => false],
            ['merchant_id' => $merchant->id]
        );
    }
}


