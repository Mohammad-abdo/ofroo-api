<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\FinancialTransaction;
use App\Models\Withdrawal;
use App\Models\Expense;
use App\Services\FinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * Get merchant wallet
     */
    public function getWallet(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $wallet = $this->financialService->getOrCreateWallet($merchant);

        return response()->json([
            'data' => [
                'balance' => (float) $wallet->balance,
                'reserved_balance' => (float) ($wallet->reserved_balance ?? 0),
                'available_balance' => (float) ($wallet->available_balance ?? ($wallet->balance - ($wallet->reserved_balance ?? 0))),
                'currency' => $wallet->currency ?? 'EGP',
                'is_frozen' => $wallet->is_frozen ?? false,
                'pending_balance' => (float) ($wallet->pending_balance ?? 0),
                'total_earned' => (float) ($wallet->total_earned ?? 0),
                'total_withdrawn' => (float) ($wallet->total_withdrawn ?? 0),
                'total_commission_paid' => (float) ($wallet->total_commission_paid ?? 0),
            ],
        ]);
    }

    /**
     * Get transaction history
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = FinancialTransaction::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('flow')) {
            $query->where('transaction_flow', $request->flow);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $transactions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get earnings report
     */
    public function getEarningsReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $period = $request->get('period', 'month'); // day, week, month, year
        $from = $request->get('from');
        $to = $request->get('to');

        if (!$from || !$to) {
            switch ($period) {
                case 'day':
                    $from = now()->startOfDay()->toDateString();
                    $to = now()->endOfDay()->toDateString();
                    break;
                case 'week':
                    $from = now()->startOfWeek()->toDateString();
                    $to = now()->endOfWeek()->toDateString();
                    break;
                case 'year':
                    $from = now()->startOfYear()->toDateString();
                    $to = now()->endOfYear()->toDateString();
                    break;
                default: // month
                    $from = now()->startOfMonth()->toDateString();
                    $to = now()->endOfMonth()->toDateString();
            }
        }

        $report = $this->financialService->getProfitLossReport($merchant, $from, $to);

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Record expense
     */
    public function recordExpense(Request $request): JsonResponse
    {
        $request->validate([
            'expense_type' => 'required|string|in:advertising,subscription,fees,other',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'expense_date' => 'nullable|date',
            'receipt_url' => 'nullable|url',
        ]);

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $expense = $this->financialService->recordExpense($merchant, $request->all());

        return response()->json([
            'message' => 'Expense recorded successfully',
            'data' => $expense,
        ], 201);
    }

    /**
     * Get expenses
     */
    public function getExpenses(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = Expense::where('merchant_id', $merchant->id)
            ->orderBy('expense_date', 'desc');

        if ($request->has('type')) {
            $query->where('expense_type', $request->type);
        }

        if ($request->has('from')) {
            $query->where('expense_date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('expense_date', '<=', $request->to);
        }

        $expenses = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $expenses->items(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    /**
     * Request withdrawal
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'withdrawal_method' => 'required|string|in:bank_transfer,paypal,other',
            'account_details' => 'nullable|string',
        ]);

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        try {
            $withdrawal = $this->financialService->requestWithdrawal($merchant, $request->all());

            return response()->json([
                'message' => 'Withdrawal request submitted successfully',
                'data' => $withdrawal,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get withdrawals
     */
    public function getWithdrawals(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = Withdrawal::where('merchant_id', $merchant->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $withdrawals->items(),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    /**
     * Get sales tracking
     */
    public function getSalesTracking(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $query = \App\Models\Order::with(['user', 'items.offer'])
            ->where('merchant_id', $merchant->id)
            ->where('payment_status', 'paid')
            ->orderBy('created_at', 'desc');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
