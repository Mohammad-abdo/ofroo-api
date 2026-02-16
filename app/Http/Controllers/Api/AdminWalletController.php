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
     * Get admin wallet transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = AdminWallet::getOrCreate();

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('wallet_type', 'admin')
            ->with('createdBy')
            ->orderBy('created_at', 'desc');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        $transactions = $query->paginate($request->get('per_page', 50));

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
