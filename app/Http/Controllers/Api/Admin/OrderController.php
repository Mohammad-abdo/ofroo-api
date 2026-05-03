<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get all orders (Admin)
     */
    public function getOrders(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['user', 'merchant'])->withCount(['items', 'coupons']);

            if ($request->filled('status')) {
                $query->where('payment_status', $request->status);
            }

            if ($request->filled('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('from')) {
                $query->where('created_at', '>=', $request->from);
            }

            if ($request->filled('to')) {
                $query->where('created_at', '<=', $request->to);
            }

            if ($request->filled('coupon_status')) {
                $status = strtolower(trim((string) $request->get('coupon_status')));
                $query->whereHas('couponEntitlements', function ($q) use ($status) {
                    switch ($status) {
                        case 'valid':
                            $q->where('status', 'active')
                                ->whereRaw('(usage_limit - times_used - reserved_shares_count) > 0')
                                ->where('times_used', 0);
                            break;
                        case 'expired':
                            $q->whereIn('status', ['expired', 'exhausted']);
                            break;
                        case 'inactive':
                            $q->where('status', 'pending');
                            break;
                        case 'activated':
                            $q->where('status', 'active')->where('times_used', '>', 0);
                            break;
                    }
                });
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->paginate((int) $request->get('per_page', 15));

            $data = $orders->getCollection()->map(function ($order) {
                $user = $order->relationLoaded('user') ? $order->user : null;
                $merchant = $order->relationLoaded('merchant') ? $order->merchant : null;

                return [
                    'id' => $order->id,
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name ?? '',
                        'email' => $user->email ?? '',
                    ] : null,
                    'merchant' => $merchant ? [
                        'id' => $merchant->id,
                        'company_name' => $merchant->company_name ?? '',
                    ] : null,
                    'total_amount' => $order->total_amount,
                    'payment_method' => $order->payment_method ?? 'cash',
                    'payment_status' => $order->payment_status ?? 'pending',
                    'items_count' => (int) ($order->items_count ?? 0),
                    'coupons_count' => (int) ($order->coupons_count ?? 0),
                    'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
                ];
            });

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Admin getOrders: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->only(['page', 'per_page', 'status', 'merchant_id', 'user_id', 'from', 'to', 'coupon_status']),
            ]);

            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) $request->get('per_page', 15),
                    'total' => 0,
                ],
                'message' => 'Orders could not be loaded. Check server logs.',
            ]);
        }
    }

    /**
     * Get single order (Admin)
     */
    public function getOrder(string $id): JsonResponse
    {
        $order = Order::with(['user', 'merchant', 'items.offer', 'coupons', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ],
                'merchant' => [
                    'id' => $order->merchant->id,
                    'company_name' => $order->merchant->company_name,
                ],
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'notes' => $order->notes,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'offer' => [
                            'id' => $item->offer->id,
                            'title_ar' => $item->offer->title_ar,
                            'title_en' => $item->offer->title_en,
                        ],
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                }),
                'coupons' => $order->coupons->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'coupon_code' => $coupon->coupon_code,
                        'status' => $coupon->status,
                    ];
                }),
                'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Create order (Admin)
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'merchant_id' => 'required|exists:merchants,id',
            'items' => 'required|array|min:1',
            'items.*.offer_id' => 'required|exists:offers,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $offer = Offer::findOrFail($itemData['offer_id']);
                $totalAmount += $offer->price * $itemData['quantity'];
            }

            $order = Order::create([
                'user_id' => $request->user_id,
                'merchant_id' => $request->merchant_id,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status ?? 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                $offer = Offer::findOrFail($itemData['offer_id']);
                $unitPrice = $offer->price;
                $quantity = $itemData['quantity'];
                $order->items()->create([
                    'offer_id' => $itemData['offer_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'data' => $order->load(['user', 'merchant', 'items']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order (Admin)
     */
    public function updateOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded,cancelled',
            'notes' => 'sometimes|string',
        ]);

        $order->update($request->only(['payment_status', 'notes']));

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => $order->fresh()->load(['user', 'merchant', 'items']),
        ]);
    }

    /**
     * Delete order (Admin)
     */
    public function deleteOrder(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete order with payments',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Cancel order (Admin)
     */
    public function cancelOrder(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Cannot cancel paid order. Please refund instead.',
            ], 422);
        }

        $order->update(['payment_status' => 'cancelled']);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Refund order (Admin)
     */
    public function refundOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'message' => 'Only paid orders can be refunded',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $order->update([
            'payment_status' => 'refunded',
            'notes' => ($order->notes ?? '')."\nRefunded: ".$request->reason,
        ]);

        return response()->json([
            'message' => 'Order refunded successfully',
            'data' => $order->fresh(),
        ]);
    }
}
