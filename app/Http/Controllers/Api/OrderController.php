<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CouponService;
use App\Services\FinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * List user orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::with(['items.offer', 'coupons', 'merchant'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get order details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['items.offer', 'coupons.offer', 'merchant'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Checkout - Create order from cart
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|in:cash,card,none',
            'cart_id' => 'required|exists:carts,id',
        ]);

        // Check if electronic payments are enabled for card payments
        if ($request->payment_method === 'card' && !\App\Services\FeatureFlagService::isElectronicPaymentsEnabled()) {
            return response()->json([
                'message' => 'Electronic payments are currently disabled',
            ], 403);
        }

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)
            ->where('id', $request->cart_id)
            ->with(['items.offer', 'items.coupon'])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate total
            $totalAmount = $cart->items->sum(function ($item) {
                return $item->price_at_add * $item->quantity;
            });

            // Determine merchant (assuming single merchant per order)
            $merchantId = $cart->items->first()->offer->merchant_id;

            $paymentMethod = $request->payment_method ?? 'cash';

            // For online payment, process payment first
            if ($paymentMethod !== 'cash') {
                // Process online payment
                $paymentGatewayService = app(\App\Services\PaymentGatewayService::class);
                $payment = $paymentGatewayService->processPayment(
                    null, // Order not created yet
                    $paymentMethod,
                    $request->payment_data ?? []
                );

                if ($payment->status !== 'success') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Payment failed',
                        'errors' => ['payment' => 'Payment could not be processed'],
                    ], 400);
                }
            }

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'merchant_id' => $merchantId,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'cash' ? 'pending' : ($payment ? $payment->status : null) ?? 'pending',
                'notes' => $request->notes,
            ]);

            // Link payment to order if exists
            if ($payment) {
                $paymentGatewayService = app(\App\Services\PaymentGatewayService::class);
                $paymentGatewayService->linkPaymentToOrder($payment, $order);
            }

            // Create order items
            foreach ($cart->items as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'offer_id' => $cartItem->offer_id,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->price_at_add,
                    'total_price' => $cartItem->price_at_add * $cartItem->quantity,
                ]);

                if ($cartItem->coupon_id) {
                    \App\Models\Coupon::where('id', $cartItem->coupon_id)->increment('times_used', 1);
                } else {
                    $cartItem->offer->consumeCoupons($cartItem->quantity);
                }
            }

            // Generate coupons (status: pending for cash, paid for online)
            $coupons = $this->couponService->createCouponsForOrder($order);

            // If payment is successful, update coupon status
            if ($order->payment_status === 'paid') {
                $this->couponService->updateCouponStatusAfterPayment($order);
            }

            // Clear cart
            $cart->items()->delete();

            DB::commit();

            // Process financial transaction if payment is paid
            if ($order->payment_status === 'paid') {
                // Use WalletService for wallet transactions
                $walletService = app(\App\Services\WalletService::class);
                $walletService->processOrderPayment($order);

                // Generate invoice
                $invoiceService = app(\App\Services\InvoiceGenerationService::class);
                $invoiceService->generateOrderInvoice($order, $user);

                // Award loyalty points
                $loyaltyService = app(\App\Services\LoyaltyService::class);
                $loyaltyService->awardPointsForOrder($order);

                // Send email with coupons
                \App\Jobs\SendOrderConfirmationEmail::dispatch($order, $user->language ?? 'ar');
            }

            // Log activity
            $activityLogService = app(\App\Services\ActivityLogService::class);
            $activityLogService->logCreate(
                $user->id,
                Order::class,
                $order->id,
                "Order #{$order->id} created with total amount {$order->total_amount} EGP"
            );

            return response()->json([
                'message' => 'Order created successfully',
                'data' => [
                    'order' => new OrderResource($order->load(['items', 'coupons'])),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user wallet coupons
     */
    public function walletCoupons(Request $request): JsonResponse
    {
        $user = $request->user();
        $coupons = Coupon::with(['offer.merchant'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CouponResource::collection($coupons->items()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Create review
     */
    public function createReview(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'merchant_id' => 'required|exists:merchants,id',
            'rating' => 'required|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'notes_ar' => 'nullable|string',
            'notes_en' => 'nullable|string',
        ]);

        $user = $request->user();

        // Verify order belongs to user
        $order = Order::where('user_id', $user->id)
            ->where('id', $request->order_id)
            ->firstOrFail();

        $review = $user->reviews()->create([
            'merchant_id' => $request->merchant_id,
            'order_id' => $order->id,
            'rating' => $request->rating,
            'notes' => $request->notes,
            'notes_ar' => $request->notes_ar,
            'notes_en' => $request->notes_en,
            'visible_to_public' => false, // Per SRS, reviews are not public
        ]);

        return response()->json([
            'message' => 'Review created successfully',
            'data' => $review,
        ], 201);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::where('user_id', $user->id)
            ->findOrFail($id);

        // Only allow cancellation if payment is pending
        if ($order->payment_status !== 'pending') {
            return response()->json([
                'message' => 'Cannot cancel order. Payment status: ' . $order->payment_status,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Cancel all coupons
            $order->coupons()->update([
                'status' => 'cancelled',
            ]);

            // Restore coupons_remaining in offers
            foreach ($order->items as $item) {
                $item->offer->increment('coupons_remaining', $item->quantity);
            }

            // Update order payment status
            $order->update([
                'payment_status' => 'failed',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order cancelled successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order cancellation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order coupons
     */
    public function getOrderCoupons(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::where('user_id', $user->id)
            ->findOrFail($id);

        $coupons = $order->coupons()
            ->with(['offer.merchant'])
            ->get();

        return response()->json([
            'data' => CouponResource::collection($coupons),
        ]);
    }
}