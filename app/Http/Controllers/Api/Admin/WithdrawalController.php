<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\FinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function withdrawals(Request $request): JsonResponse
    {
        $query = Withdrawal::with(['merchant', 'approver']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('merchant')) {
            $query->where('merchant_id', $request->merchant);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $data = $withdrawals->getCollection()->map(function ($withdrawal) {
            return [
                'id' => $withdrawal->id,
                'merchant_id' => $withdrawal->merchant_id,
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status,
                'bank_account' => $withdrawal->bank_account ?? null,
                'bank_name' => $withdrawal->bank_name ?? null,
                'account_holder' => $withdrawal->account_holder ?? null,
                'merchant' => [
                    'id' => $withdrawal->merchant->id,
                    'company_name' => $withdrawal->merchant->company_name,
                ],
                'approved_at' => $withdrawal->approved_at ? $withdrawal->approved_at->toIso8601String() : null,
                'created_at' => $withdrawal->created_at ? $withdrawal->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    public function approveWithdrawal(Request $request, string $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);
        $adminId = $request->user()->id;

        $financialService = app(FinancialService::class);
        $financialService->approveWithdrawal($withdrawal, $adminId);

        return response()->json([
            'message' => 'Withdrawal approved successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }

    public function rejectWithdrawal(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $withdrawal = Withdrawal::findOrFail($id);
        $adminId = $request->user()->id;

        $financialService = app(FinancialService::class);
        $financialService->rejectWithdrawal($withdrawal, $adminId, $request->reason);

        return response()->json([
            'message' => 'Withdrawal rejected successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }

    public function completeWithdrawal(string $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        $financialService = app(FinancialService::class);
        $financialService->completeWithdrawal($withdrawal);

        return response()->json([
            'message' => 'Withdrawal completed successfully',
            'data' => $withdrawal->fresh(),
        ]);
    }
}
