<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminWallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get admin wallet summary
     */
    public function index(): JsonResponse
    {
        $wallet = AdminWallet::getOrCreate();

        $pendingWithdrawals = \App\Models\Withdrawal::where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'data' => [
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'pending_withdrawals' => $pendingWithdrawals,
                'available_balance' => $wallet->balance - $pendingWithdrawals,
            ],
        ]);
    }

    /**
     * Get wallet transactions (admin wallet and/or all merchant wallets).
     *
     * Query: wallet_type=admin|merchant, type (transaction_type), from/from_date, to/to_date, search (note), per_page, page
     */
    public function transactions(Request $request): JsonResponse
    {
        $walletType = $request->get('wallet_type', 'admin');

        $query = WalletTransaction::query()
            ->with('createdBy')
            ->orderBy('created_at', 'desc');

        if ($walletType === 'merchant') {
            $query->where('wallet_type', 'merchant');
        } else {
            $wallet = AdminWallet::getOrCreate();
            $query->where('wallet_id', $wallet->id)
                ->where('wallet_type', 'admin');
        }

        $from = $request->get('from_date') ?? $request->get('from');
        $to = $request->get('to_date') ?? $request->get('to');
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', str_contains((string) $to, ' ') ? $to : $to.' 23:59:59');
        }

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->search, '%_\\').'%';
            $query->where('note', 'like', $term);
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));

        $transactions = $query->paginate($perPage);

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
     * Adjust wallet (credit/debit)
     */
    public function adjust(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_type' => 'required|in:merchant,admin',
            'wallet_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'action' => 'required|in:debit,credit',
            'reason' => 'required|string|min:10',
        ]);

        $admin = $request->user();

        try {
            if ($request->wallet_type === 'admin') {
                $wallet = AdminWallet::findOrFail($request->wallet_id);
                if ($request->action === 'credit') {
                    $transaction = $this->walletService->creditAdminWallet(
                        $request->amount,
                        'adjustment',
                        null,
                        $request->reason,
                        $admin
                    );
                } else {
                    $transaction = $this->walletService->debitAdminWallet(
                        $request->amount,
                        'adjustment',
                        null,
                        $request->reason,
                        $admin
                    );
                }
            } else {
                $merchant = \App\Models\Merchant::findOrFail($request->wallet_id);
                if ($request->action === 'credit') {
                    $transaction = $this->walletService->creditMerchantWallet(
                        $merchant,
                        $request->amount,
                        'adjustment',
                        null,
                        $request->reason,
                        $admin
                    );
                } else {
                    $transaction = $this->walletService->debitMerchantWallet(
                        $merchant,
                        $request->amount,
                        'adjustment',
                        null,
                        $request->reason,
                        $admin
                    );
                }
            }

            return response()->json([
                'message' => 'Wallet adjusted successfully',
                'data' => $transaction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Adjustment failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get merchant wallet (Admin view)
     */
    public function getMerchantWallet(string $merchantId): JsonResponse
    {
        $merchant = \App\Models\Merchant::findOrFail($merchantId);
        $wallet = \App\Models\MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
        );

        return response()->json([
            'data' => [
                'merchant_id' => $merchant->id,
                'merchant_name' => $merchant->company_name,
                'balance' => $wallet->balance,
                'reserved_balance' => $wallet->reserved_balance,
                'available_balance' => $wallet->available_balance,
                'currency' => $wallet->currency,
                'is_frozen' => $wallet->is_frozen,
            ],
        ]);
    }
}
