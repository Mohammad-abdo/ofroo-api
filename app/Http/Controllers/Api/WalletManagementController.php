<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminWallet;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletManagementController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(): JsonResponse
    {
        $adminWallet = AdminWallet::getOrCreate();

        $stats = Cache::remember('wallet_stats', 300, function () use ($adminWallet) {
            $pendingWithdrawals = Withdrawal::where('status', 'pending')->sum('amount');
            $todayCommission = WalletTransaction::where('wallet_type', 'admin')
                ->where('transaction_type', 'commission')
                ->whereDate('created_at', today())
                ->sum('amount');

            return [
                'balance' => (float) $adminWallet->balance,
                'currency' => $adminWallet->currency,
                'pending_withdrawals' => (float) $pendingWithdrawals,
                'today_commission' => (float) $todayCommission,
                'total_withdrawals' => Withdrawal::whereIn('status', ['completed'])->sum('amount'),
                'total_transactions' => WalletTransaction::where('wallet_type', 'admin')->count(),
            ];
        });

        $recentTransactions = WalletTransaction::with(['creator'])
            ->where('wallet_type', 'admin')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->transaction_type,
                'amount' => (float) $t->amount,
                'note' => $t->note,
                'created_by' => $t->creator?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'stats' => $stats,
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = WalletTransaction::with(['creator', 'wallet'])
            ->where('wallet_type', $request->get('wallet_type', 'admin'));

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                    ->orWhere('related_id', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->transaction_type,
                'wallet_type' => $t->wallet_type,
                'wallet_id' => $t->wallet_id,
                'related_type' => $t->related_type,
                'related_id' => $t->related_id,
                'amount' => (float) $t->amount,
                'balance_before' => (float) $t->balance_before,
                'balance_after' => (float) $t->balance_after,
                'note' => $t->note,
                'created_by' => $t->creator?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function merchantWallet(Request $request, string $merchantId): JsonResponse
    {
        $merchant = Merchant::with('user')->findOrFail($merchantId);
        $wallet = MerchantWallet::where('merchant_id', $merchantId)->first();

        if (!$wallet) {
            return response()->json([
                'message' => 'Wallet not found',
                'data' => [
                    'merchant' => [
                        'id' => $merchant->id,
                        'name' => $merchant->company_name,
                        'email' => $merchant->user?->email,
                    ],
                    'wallet' => null,
                ],
            ]);
        }

        $recentTransactions = WalletTransaction::with(['creator'])
            ->where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'merchant' => [
                    'id' => $merchant->id,
                    'name' => $merchant->company_name,
                    'email' => $merchant->user?->email,
                    'approved' => $merchant->approved,
                ],
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->balance,
                    'available_balance' => (float) ($wallet->balance - $wallet->reserved_balance),
                    'reserved_balance' => (float) $wallet->reserved_balance,
                    'pending_balance' => (float) $wallet->pending_balance,
                    'total_earned' => (float) $wallet->total_earned,
                    'total_withdrawn' => (float) $wallet->total_withdrawn,
                    'total_commission_paid' => (float) $wallet->total_commission_paid,
                    'is_frozen' => $wallet->is_frozen,
                    'currency' => $wallet->currency,
                ],
                'recent_transactions' => $recentTransactions->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->transaction_type,
                    'amount' => (float) $t->amount,
                    'note' => $t->note,
                    'created_at' => $t->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required_without:wallet_type|exists:merchants,id',
            'wallet_type' => 'required_without:merchant_id|in:admin',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:credit,debit',
            'note' => 'required|string|max:500',
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            if ($request->merchant_id) {
                $merchant = Merchant::findOrFail($request->merchant_id);

                if ($request->type === 'credit') {
                    $this->walletService->creditMerchantWallet(
                        $merchant,
                        $request->amount,
                        'adjustment',
                        null,
                        $request->note,
                        $user
                    );
                } else {
                    $this->walletService->debitMerchantWallet(
                        $merchant,
                        $request->amount,
                        'adjustment',
                        null,
                        $request->note,
                        $user
                    );
                }

                $wallet = MerchantWallet::where('merchant_id', $merchant->id)->first();

                DB::commit();

                return response()->json([
                    'message' => 'Merchant wallet adjusted successfully',
                    'data' => [
                        'new_balance' => (float) $wallet->balance,
                    ],
                ]);
            } else {
                if ($request->type === 'credit') {
                    $this->walletService->creditAdminWallet(
                        $request->amount,
                        'adjustment',
                        null,
                        $request->note,
                        $user
                    );
                } else {
                    $this->walletService->debitAdminWallet(
                        $request->amount,
                        'adjustment',
                        null,
                        $request->note,
                        $user
                    );
                }

                DB::commit();

                return response()->json([
                    'message' => 'Admin wallet adjusted successfully',
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Adjustment failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function freezeMerchant(Request $request, string $merchantId): JsonResponse
    {
        $merchant = Merchant::findOrFail($merchantId);
        $user = $request->user();

        $this->walletService->freezeWallet($merchant, $user);

        return response()->json([
            'message' => 'Merchant wallet frozen successfully',
        ]);
    }

    public function unfreezeMerchant(Request $request, string $merchantId): JsonResponse
    {
        $merchant = Merchant::findOrFail($merchantId);
        $user = $request->user();

        $this->walletService->unfreezeWallet($merchant, $user);

        return response()->json([
            'message' => 'Merchant wallet unfrozen successfully',
        ]);
    }

    public function settings(): JsonResponse
    {
        $minimumWithdrawal = (float) \App\Models\Setting::getValue('minimum_withdrawal', 100);
        $withdrawalFee = (float) \App\Models\Setting::getValue('withdrawal_fee', 0);
        $withdrawalFeePercent = (float) \App\Models\Setting::getValue('withdrawal_fee_percent', 0);
        $commissionRate = (float) \App\Models\Setting::getValue('commission_rate', 0.10);

        return response()->json([
            'data' => [
                'minimum_withdrawal' => $minimumWithdrawal,
                'withdrawal_fee' => $withdrawalFee,
                'withdrawal_fee_percent' => $withdrawalFeePercent,
                'commission_rate' => round($commissionRate * 100, 2),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'minimum_withdrawal' => 'nullable|numeric|min:0',
            'withdrawal_fee' => 'nullable|numeric|min:0',
            'withdrawal_fee_percent' => 'nullable|numeric|min:0|max:100',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->has('minimum_withdrawal')) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'minimum_withdrawal'],
                ['value' => $request->minimum_withdrawal, 'type' => 'float']
            );
        }

        if ($request->has('withdrawal_fee')) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'withdrawal_fee'],
                ['value' => $request->withdrawal_fee, 'type' => 'float']
            );
        }

        if ($request->has('withdrawal_fee_percent')) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'withdrawal_fee_percent'],
                ['value' => $request->withdrawal_fee_percent, 'type' => 'float']
            );
        }

        if ($request->has('commission_rate')) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'commission_rate'],
                ['value' => $request->commission_rate / 100, 'type' => 'float']
            );
        }

        Cache::forget('wallet_stats');

        return response()->json([
            'message' => 'Settings updated successfully',
        ]);
    }

    public function allMerchantWallets(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = MerchantWallet::with('merchant.user')
            ->whereHas('merchant');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('merchant', function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_name_ar', 'like', "%{$search}%");
            });
        }

        if ($request->filled('has_balance')) {
            $query->where('balance', '>', 0);
        }

        if ($request->filled('is_frozen')) {
            $query->where('is_frozen', $request->boolean('is_frozen'));
        }

        $wallets = $query->orderBy('balance', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $wallets->map(fn ($w) => [
                'id' => $w->id,
                'merchant_id' => $w->merchant_id,
                'merchant_name' => $w->merchant?->company_name,
                'balance' => (float) $w->balance,
                'available_balance' => (float) ($w->balance - $w->reserved_balance),
                'reserved_balance' => (float) $w->reserved_balance,
                'total_earned' => (float) $w->total_earned,
                'total_withdrawn' => (float) $w->total_withdrawn,
                'is_frozen' => $w->is_frozen,
                'created_at' => $w->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $wallets->currentPage(),
                'last_page' => $wallets->lastPage(),
                'per_page' => $wallets->perPage(),
                'total' => $wallets->total(),
            ],
        ]);
    }
}
