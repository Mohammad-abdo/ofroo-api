<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Expense;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialService
{
    /**
     * Get or create merchant wallet
     */
    public function getOrCreateWallet(Merchant $merchant): MerchantWallet
    {
        return MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'balance' => 0,
                'pending_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_commission_paid' => 0,
            ]
        );
    }

    /**
     * Process order payment and update wallet (Legacy - Use WalletService instead)
     */
    public function processOrderPayment(Order $order): void
    {
        // Use WalletService for processing
        $walletService = app(WalletService::class);
        $walletService->processOrderPayment($order);
    }

    /**
     * Record expense
     */
    public function recordExpense(Merchant $merchant, array $data): Expense
    {
        $wallet = $this->getOrCreateWallet($merchant);

        DB::beginTransaction();
        try {
            $expense = Expense::create([
                'merchant_id' => $merchant->id,
                'expense_type' => $data['expense_type'],
                'expense_type_ar' => $data['expense_type_ar'] ?? null,
                'expense_type_en' => $data['expense_type_en'] ?? null,
                'category' => $data['category'] ?? null,
                'category_ar' => $data['category_ar'] ?? null,
                'category_en' => $data['category_en'] ?? null,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'description_ar' => $data['description_ar'] ?? null,
                'description_en' => $data['description_en'] ?? null,
                'expense_date' => $data['expense_date'] ?? now(),
                'receipt_url' => $data['receipt_url'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Create transaction
            FinancialTransaction::create([
                'merchant_id' => $merchant->id,
                'transaction_type' => 'expense',
                'transaction_flow' => 'outgoing',
                'amount' => $data['amount'],
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance - $data['amount'],
                'description' => "Expense: {$data['description']}",
                'description_ar' => "مصروف: {$data['description_ar']}",
                'description_en' => "Expense: {$data['description']}",
                'reference_number' => "EXP-{$expense->id}",
                'metadata' => ['expense_id' => $expense->id],
                'status' => 'completed',
            ]);

            // Update wallet
            $wallet->decrement('balance', $data['amount']);

            DB::commit();
            return $expense;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Request withdrawal
     */
    public function requestWithdrawal(Merchant $merchant, array $data): Withdrawal
    {
        $wallet = $this->getOrCreateWallet($merchant);

        if ($wallet->balance < $data['amount']) {
            throw new \Exception('Insufficient balance');
        }

        DB::beginTransaction();
        try {
            $withdrawal = Withdrawal::create([
                'merchant_id' => $merchant->id,
                'amount' => $data['amount'],
                'withdrawal_method' => $data['withdrawal_method'],
                'account_details' => $data['account_details'] ?? null,
                'status' => 'pending',
            ]);

            // Move balance to pending
            $wallet->decrement('balance', $data['amount']);
            $wallet->increment('pending_balance', $data['amount']);

            // Create transaction
            FinancialTransaction::create([
                'merchant_id' => $merchant->id,
                'transaction_type' => 'withdrawal',
                'transaction_flow' => 'outgoing',
                'amount' => $data['amount'],
                'balance_before' => $wallet->balance + $data['amount'],
                'balance_after' => $wallet->balance,
                'description' => "Withdrawal request #{$withdrawal->id}",
                'description_ar' => "طلب سحب رقم {$withdrawal->id}",
                'description_en' => "Withdrawal request #{$withdrawal->id}",
                'reference_number' => "WD-{$withdrawal->id}",
                'metadata' => ['withdrawal_id' => $withdrawal->id],
                'status' => 'pending',
            ]);

            DB::commit();
            return $withdrawal;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve withdrawal
     */
    public function approveWithdrawal(Withdrawal $withdrawal, int $adminId): void
    {
        $wallet = $this->getOrCreateWallet($withdrawal->merchant);

        DB::beginTransaction();
        try {
            $withdrawal->update([
                'status' => 'approved',
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            // Move from pending to withdrawn
            $wallet->decrement('pending_balance', $withdrawal->amount);
            $wallet->increment('total_withdrawn', $withdrawal->amount);

            // Update transaction
            FinancialTransaction::where('reference_number', "WD-{$withdrawal->id}")
                ->update(['status' => 'completed']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete withdrawal
     */
    public function completeWithdrawal(Withdrawal $withdrawal): void
    {
        $withdrawal->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Reject withdrawal
     */
    public function rejectWithdrawal(Withdrawal $withdrawal, int $adminId, string $reason): void
    {
        $wallet = $this->getOrCreateWallet($withdrawal->merchant);

        DB::beginTransaction();
        try {
            $withdrawal->update([
                'status' => 'rejected',
                'approved_by' => $adminId,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Return balance
            $wallet->decrement('pending_balance', $withdrawal->amount);
            $wallet->increment('balance', $withdrawal->amount);

            // Update transaction
            FinancialTransaction::where('reference_number', "WD-{$withdrawal->id}")
                ->update(['status' => 'failed']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get merchant profit & loss report
     */
    public function getProfitLossReport(Merchant $merchant, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to = $to ?: now()->endOfMonth()->toDateString();

        // Revenue (incoming transactions)
        $revenue = FinancialTransaction::where('merchant_id', $merchant->id)
            ->where('transaction_flow', 'incoming')
            ->where('transaction_type', 'order_revenue')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // Expenses
        $expenses = Expense::where('merchant_id', $merchant->id)
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        // Commission paid
        $commission = FinancialTransaction::where('merchant_id', $merchant->id)
            ->where('transaction_type', 'commission')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // Withdrawals
        $withdrawals = Withdrawal::where('merchant_id', $merchant->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->sum('amount');

        $totalExpenses = $expenses + $commission + $withdrawals;
        $netProfit = $revenue - $totalExpenses;

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'revenue' => [
                'total' => $revenue,
                'orders_count' => FinancialTransaction::where('merchant_id', $merchant->id)
                    ->where('transaction_type', 'order_revenue')
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
            ],
            'expenses' => [
                'total' => $expenses,
                'advertising' => Expense::where('merchant_id', $merchant->id)
                    ->where('expense_type', 'advertising')
                    ->whereBetween('expense_date', [$from, $to])
                    ->sum('amount'),
                'subscription' => Expense::where('merchant_id', $merchant->id)
                    ->where('expense_type', 'subscription')
                    ->whereBetween('expense_date', [$from, $to])
                    ->sum('amount'),
                'fees' => Expense::where('merchant_id', $merchant->id)
                    ->where('expense_type', 'fees')
                    ->whereBetween('expense_date', [$from, $to])
                    ->sum('amount'),
                'other' => Expense::where('merchant_id', $merchant->id)
                    ->where('expense_type', 'other')
                    ->whereBetween('expense_date', [$from, $to])
                    ->sum('amount'),
            ],
            'commission' => [
                'total' => $commission,
                'rate' => \App\Services\FeatureFlagService::getCommissionRate(),
            ],
            'withdrawals' => [
                'total' => $withdrawals,
                'count' => Withdrawal::where('merchant_id', $merchant->id)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$from, $to])
                    ->count(),
            ],
            'profit_loss' => [
                'total_revenue' => $revenue,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'profit_margin' => $revenue > 0 ? ($netProfit / $revenue) * 100 : 0,
            ],
        ];
    }
}

