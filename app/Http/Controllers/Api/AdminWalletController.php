<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminWallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            // For merchant adjustments, wallet_id is the Merchant ID.
            // For admin adjustments, the wallet is always the singleton AdminWallet::getOrCreate(),
            // so wallet_id is not required (keeps frontend simpler and avoids guessing the ID).
            'wallet_id' => [
                Rule::requiredIf(fn () => $request->wallet_type === 'merchant'),
                'integer',
            ],
            'amount' => 'required|numeric|min:0.01',
            'action' => 'required|in:debit,credit',
            'reason' => 'required|string|min:10',
        ]);

        $admin = $request->user();

        try {
            if ($request->wallet_type === 'admin') {
                $wallet = AdminWallet::getOrCreate();
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
     * Get merchant wallet detail (Admin view) — enriched with recent transactions
     */
    public function getMerchantWallet(string $merchantId): JsonResponse
    {
        $merchant = \App\Models\Merchant::findOrFail($merchantId);
        $wallet = \App\Models\MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
        );

        $recent = WalletTransaction::where('wallet_type', 'merchant')
            ->where('wallet_id', $merchant->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'merchant' => [
                    'id'           => $merchant->id,
                    'name'         => $merchant->company_name ?? $merchant->company_name_ar ?? $merchant->company_name_en ?? '',
                    'company_name' => $merchant->company_name ?? $merchant->company_name_ar ?? '',
                    'email'        => optional($merchant->user)->email,
                    'phone'        => optional($merchant->user)->phone,
                ],
                'wallet' => [
                    'id'                => $wallet->id,
                    'balance'           => (float) $wallet->balance,
                    'reserved_balance'  => (float) ($wallet->reserved_balance ?? 0),
                    'available_balance' => (float) ($wallet->available_balance ?? ($wallet->balance - ($wallet->reserved_balance ?? 0))),
                    'currency'          => $wallet->currency ?? 'EGP',
                    'is_frozen'         => (bool) $wallet->is_frozen,
                    'total_earned'      => (float) ($wallet->total_earned ?? 0),
                    'total_withdrawn'   => (float) ($wallet->total_withdrawn ?? 0),
                ],
                'recent_transactions' => $recent,
            ],
        ]);
    }

    /**
     * List all merchant wallets
     */
    public function getMerchantWallets(Request $request): JsonResponse
    {
        $query = \App\Models\Merchant::with('wallet')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $term = '%' . addcslashes($request->search, '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', $term)
                  ->orWhere('company_name_ar', 'like', $term)
                  ->orWhere('company_name_en', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $merchants = $query->paginate($perPage);

        $items = $merchants->getCollection()->map(function ($m) {
            $wallet = $m->wallet;
            return [
                'merchant_id'       => $m->id,
                'merchant_name'     => $m->company_name ?? $m->company_name_ar ?? $m->company_name_en ?? '',
                'balance'           => $wallet ? (float) $wallet->balance : 0,
                'reserved_balance'  => $wallet ? (float) ($wallet->reserved_balance ?? 0) : 0,
                'available_balance' => $wallet ? (float) ($wallet->available_balance ?? ($wallet->balance - ($wallet->reserved_balance ?? 0))) : 0,
                'currency'          => $wallet ? ($wallet->currency ?? 'EGP') : 'EGP',
                'is_frozen'         => $wallet ? (bool) $wallet->is_frozen : false,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $merchants->currentPage(),
                'last_page'    => $merchants->lastPage(),
                'per_page'     => $merchants->perPage(),
                'total'        => $merchants->total(),
            ],
        ]);
    }

    /**
     * Freeze merchant wallet
     */
    public function freezeMerchantWallet(string $merchantId): JsonResponse
    {
        $merchant = \App\Models\Merchant::findOrFail($merchantId);
        $wallet = \App\Models\MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'currency' => 'EGP']
        );
        $wallet->update(['is_frozen' => true]);

        return response()->json(['message' => 'Wallet frozen successfully', 'data' => ['is_frozen' => true]]);
    }

    /**
     * Unfreeze merchant wallet
     */
    public function unfreezeMerchantWallet(string $merchantId): JsonResponse
    {
        $merchant = \App\Models\Merchant::findOrFail($merchantId);
        $wallet = \App\Models\MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'currency' => 'EGP']
        );
        $wallet->update(['is_frozen' => false]);

        return response()->json(['message' => 'Wallet unfrozen successfully', 'data' => ['is_frozen' => false]]);
    }

    /**
     * Get wallet settings (e.g. min withdrawal, commission rates)
     */
    public function getSettings(Request $request): JsonResponse
    {
        $settings = \App\Models\SystemSetting::whereIn('key', [
            'min_withdrawal_amount',
            'max_withdrawal_amount',
            'withdrawal_fee',
            'commission_rate',
            'wallet_currency',
        ])->pluck('value', 'key');

        return response()->json(['data' => $settings]);
    }

    /**
     * Update wallet settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'min_withdrawal_amount' => 'sometimes|numeric|min:0',
            'max_withdrawal_amount' => 'sometimes|numeric|min:0',
            'withdrawal_fee'        => 'sometimes|numeric|min:0',
            'commission_rate'       => 'sometimes|numeric|min:0|max:100',
            'wallet_currency'       => 'sometimes|string|max:10',
        ]);

        foreach ($data as $key => $value) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated', 'data' => $data]);
    }

    /**
     * List all merchant withdrawal requests
     */
    public function getWithdrawals(Request $request): JsonResponse
    {
        $query = \App\Models\Withdrawal::with('merchant')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
        $withdrawals = $query->paginate($perPage);

        return response()->json([
            'data' => $withdrawals->items(),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page'    => $withdrawals->lastPage(),
                'per_page'     => $withdrawals->perPage(),
                'total'        => $withdrawals->total(),
            ],
        ]);
    }

    /**
     * Get single withdrawal request
     */
    public function getWithdrawal(string $id): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::with('merchant')->findOrFail($id);
        return response()->json(['data' => $withdrawal]);
    }

    /**
     * Approve a withdrawal and debit the merchant wallet
     */
    public function approveWithdrawal(Request $request, string $id): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => 'Withdrawal is not pending'], 400);
        }

        try {
            $merchant = \App\Models\Merchant::findOrFail($withdrawal->merchant_id);
            $this->walletService->debitMerchantWallet(
                $merchant,
                $withdrawal->amount,
                'withdrawal',
                null,
                'Withdrawal approved: ' . ($request->input('note') ?? ''),
                $request->user()
            );
            $withdrawal->update(['status' => 'approved', 'processed_at' => now(), 'processed_by' => $request->user()->id]);

            return response()->json(['message' => 'Withdrawal approved', 'data' => $withdrawal->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Approval failed: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Reject a withdrawal
     */
    public function rejectWithdrawal(Request $request, string $id): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => 'Withdrawal is not pending'], 400);
        }

        $withdrawal->update([
            'status'       => 'rejected',
            'processed_at' => now(),
            'processed_by' => $request->user()->id,
            'reject_reason' => $request->input('reason') ?? '',
        ]);

        return response()->json(['message' => 'Withdrawal rejected', 'data' => $withdrawal->fresh()]);
    }
}
