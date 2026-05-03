<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Get all payments (Admin)
     */
    public function getPayments(Request $request): JsonResponse
    {
        $query = Payment::with(['order.user', 'order.merchant']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('gateway')) {
            $query->where('gateway', $request->gateway);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->filled('merchant_id')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('merchant_id', $request->merchant_id);
            });
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $payments->getCollection(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get single payment (Admin)
     */
    public function getPayment(string $id): JsonResponse
    {
        $payment = Payment::with(['order.user', 'order.merchant'])
            ->findOrFail($id);

        return response()->json([
            'data' => $payment,
        ]);
    }

    /**
     * Create payment (Admin)
     */
    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'gateway' => 'required|string',
            'status' => 'nullable|in:pending,completed,failed,refunded',
        ]);

        $payment = Payment::create([
            'order_id' => $request->order_id,
            'amount' => $request->amount,
            'gateway' => $request->gateway,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => $payment,
        ], 201);
    }

    /**
     * Update payment (Admin)
     */
    public function updatePayment(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:pending,completed,failed,refunded',
        ]);

        $payment->update($request->only(['status']));

        return response()->json([
            'message' => 'Payment updated successfully',
            'data' => $payment->fresh(),
        ]);
    }

    /**
     * Delete payment (Admin)
     */
    public function deletePayment(string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status === 'completed') {
            return response()->json([
                'message' => 'Cannot delete completed payment',
            ], 422);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }

    /**
     * Refund payment (Admin)
     */
    public function refundPayment(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed payments can be refunded',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $payment->update(['status' => 'refunded']);

        return response()->json([
            'message' => 'Payment refunded successfully',
            'data' => $payment->fresh(),
        ]);
    }
}
