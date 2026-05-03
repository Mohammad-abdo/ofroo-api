<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    /**
     * Get all transactions (Admin)
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $query = FinancialTransaction::with(['merchant', 'order', 'payment']);

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('flow')) {
            $query->where('transaction_flow', $request->flow);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $transactions->getCollection(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get single transaction (Admin)
     */
    public function getTransaction(string $id): JsonResponse
    {
        $transaction = FinancialTransaction::with(['merchant', 'order', 'payment'])
            ->findOrFail($id);

        return response()->json([
            'data' => $transaction,
        ]);
    }

    /**
     * Create transaction (Admin)
     */
    public function createTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'transaction_type' => 'required|string',
            'transaction_flow' => 'required|in:incoming,outgoing',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $transaction = FinancialTransaction::create([
            'merchant_id' => $request->merchant_id,
            'order_id' => $request->order_id,
            'payment_id' => $request->payment_id,
            'transaction_type' => $request->transaction_type,
            'transaction_flow' => $request->transaction_flow,
            'amount' => $request->amount,
            'description' => $request->description,
            'status' => 'completed',
        ]);

        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Update transaction (Admin)
     */
    public function updateTransaction(Request $request, string $id): JsonResponse
    {
        $transaction = FinancialTransaction::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:pending,completed,failed',
            'description' => 'sometimes|string',
        ]);

        $transaction->update($request->only(['status', 'description']));

        return response()->json([
            'message' => 'Transaction updated successfully',
            'data' => $transaction->fresh(),
        ]);
    }

    /**
     * Delete transaction (Admin)
     */
    public function deleteTransaction(string $id): JsonResponse
    {
        $transaction = FinancialTransaction::findOrFail($id);

        if ($transaction->status === 'completed') {
            return response()->json([
                'message' => 'Cannot delete completed transaction',
            ], 422);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }
}
