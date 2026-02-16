<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletTransactionController extends Controller
{
    /**
     * Get merchant wallet transactions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();
        
        $wallet = MerchantWallet::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'EGP', 'is_frozen' => false]
        );

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('wallet_type', 'merchant')
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
     * Export transactions
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();
        
        $wallet = MerchantWallet::where('merchant_id', $merchant->id)->firstOrFail();

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('wallet_type', 'merchant')
            ->orderBy('created_at', 'desc');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $transactions = $query->get();

        $format = $request->get('format', 'csv');

        if ($format === 'csv') {
            return $this->exportCsv($transactions);
        } elseif ($format === 'xlsx') {
            return $this->exportExcel($transactions);
        }

        return response()->json(['message' => 'Invalid format'], 400);
    }

    protected function exportCsv($transactions)
    {
        $filename = 'transactions_' . now()->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Type', 'Amount', 'Balance Before', 'Balance After', 'Note']);

            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->transaction_type,
                    $transaction->amount,
                    $transaction->balance_before,
                    $transaction->balance_after,
                    $transaction->note,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function exportExcel($transactions)
    {
        // Use Maatwebsite/Excel for Excel export
        // This is a placeholder
        return response()->json(['message' => 'Excel export not implemented'], 501);
    }
}
