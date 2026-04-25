<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\CouponEntitlementResource;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CouponService;
use App\Services\FinancialService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * Localised labels for the `coupon_status` filter so the mobile UI
     * can render a bilingual legend without duplicating strings.
     *
     * @return array<string, array{ar: string, en: string}>
     */
    public static function couponStatusLabels(): array
    {
        return [
            'valid' => ['ar' => 'صالح', 'en' => 'Valid'],
            'expired' => ['ar' => 'منتهي', 'en' => 'Expired'],
            'inactive' => ['ar' => 'غير مفعل', 'en' => 'Inactive'],
            'activated' => ['ar' => 'تم تفعيله', 'en' => 'Activated'],
        ];
    }

    /**
     * Apply the {@see couponStatusLabels()} filter to an Order query via
     * the `couponEntitlements` relation. No-op when $status is empty or unknown.
     */
    protected function applyCouponStatusFilter($query, ?string $status)
    {
        $status = $status !== null ? strtolower(trim($status)) : '';
        if ($status === '') {
            return $query;
        }

        return $query->whereHas('couponEntitlements', function ($q) use ($status) {
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
                default:
                    // Unknown value → do not constrain; keep previous behaviour.
                    break;
            }
        });
    }

    /**
     * List user orders.
     *
     * Accepts an optional `coupon_status` filter (valid|expired|inactive|activated)
     * that filters orders by the status of their coupon entitlements.
     * When omitted, the response shape is identical to the previous version.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::with(['items.offer', 'coupons', 'couponEntitlements.coupon', 'merchant'])
            ->where('user_id', $user->id);

        $query = $this->applyCouponStatusFilter($query, $request->get('coupon_status'));

        $orders = $query->orderBy('created_at', 'desc')
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
        $order = Order::with(['items.offer', 'coupons.offer', 'couponEntitlements.coupon', 'merchant'])
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
        // Normalize common mobile aliases before validation.
        if (! $request->filled('payment_method') && $request->filled('payment_method_type')) {
            $request->merge([
                'payment_method' => $request->input('payment_method_type'),
            ]);
        }

        $normalizedPaymentMethod = strtolower((string) ($request->input('payment_method') ?? ''));
        if ($normalizedPaymentMethod === 'cash_on_delivery') {
            $request->merge(['payment_method' => 'cash']);
        }

        // Validate the checkout request before touching cart data.
        $request->validate([
            'payment_method' => 'required|in:cash,card,none',
            'cart_id' => 'nullable|exists:carts,id',
        ]);

        // Check if electronic payments are enabled for card payments
        if ($request->payment_method === 'card' && !\App\Services\FeatureFlagService::isElectronicPaymentsEnabled()) {
            return response()->json([
                'message' => 'Electronic payments are currently disabled',
            ], 403);
        }

        // Load the authenticated user's cart snapshot for checkout processing.
        $user = $request->user();
        $cartQuery = Cart::where('user_id', $user->id)
            ->with(['items.offer', 'items.coupon']);

        if ($request->filled('cart_id')) {
            $cartQuery->where('id', $request->cart_id);
        } else {
            // Backward compatible fallback: use the user's latest cart when cart_id is omitted.
            $cartQuery->orderByDesc('id');
        }

        $cart = $cartQuery->firstOrFail();

        if ($cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty',
            ], 400);
        }

        $paymentMethod = $request->payment_method ?? 'cash';
        $overallTotalAmount = (float) $cart->items->sum(function ($item) {
            return $item->price_at_add * $item->quantity;
        });

        // Group cart lines by merchant to support mixed offers from different merchants.
        $groupedByMerchant = $cart->items->groupBy(function ($item) {
            return (string) ($item->offer->merchant_id ?? '');
        });

        if ($groupedByMerchant->has('')) {
            return response()->json([
                'message' => 'One or more cart items are not linked to a merchant',
            ], 422);
        }

        // Current payment flow supports one online payment intent; mixed-merchant carts must checkout as cash.
        if ($paymentMethod !== 'cash' && $groupedByMerchant->count() > 1) {
            return response()->json([
                'message' => 'Online checkout for multiple merchants is not supported. Please use cash or split the cart.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $payment = null;
            if ($paymentMethod !== 'cash') {
                $paymentGatewayService = app(\App\Services\PaymentGatewayService::class);
                $paymentData = $request->payment_data ?? [];
                if (! isset($paymentData['amount'])) {
                    $paymentData['amount'] = $overallTotalAmount;
                }
                $payment = $paymentGatewayService->processPayment(
                    null, // Order not created yet
                    $paymentMethod,
                    $paymentData
                );

                if ($payment->status !== 'success') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Payment failed',
                        'errors' => ['payment' => 'Payment could not be processed'],
                    ], 400);
                }
            }

            $orders = collect();

            foreach ($groupedByMerchant as $merchantId => $merchantItems) {
                $merchantTotal = $merchantItems->sum(function ($item) {
                    return $item->price_at_add * $item->quantity;
                });

                $order = Order::create([
                    'user_id' => $user->id,
                    'merchant_id' => (int) $merchantId,
                    'total_amount' => $merchantTotal,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentMethod === 'cash' ? 'pending' : ($payment ? $payment->status : null) ?? 'pending',
                    'notes' => $request->notes,
                ]);

                // Link payment when a single online payment is used.
                if (isset($payment)) {
                    app(\App\Services\PaymentGatewayService::class)->linkPaymentToOrder($payment, $order);
                }

                foreach ($merchantItems as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'offer_id' => $cartItem->offer_id,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $cartItem->price_at_add,
                        'total_price' => $cartItem->price_at_add * $cartItem->quantity,
                    ]);

                    if ($cartItem->coupon_id) {
                        \App\Models\Coupon::where('id', $cartItem->coupon_id)->increment('times_used', (int) $cartItem->quantity);
                    } else {
                        $cartItem->offer->consumeCoupons($cartItem->quantity);
                    }
                }

                $this->couponService->createCouponsForOrder($order);
                if ($order->payment_status === 'paid') {
                    $this->couponService->updateCouponStatusAfterPayment($order);
                }

                $orders->push($order);
            }

            // Empty the cart only after all merchant orders are created.
            $cart->items()->delete();

            DB::commit();

            $walletService = app(\App\Services\WalletService::class);
            $invoiceService = app(\App\Services\InvoiceGenerationService::class);
            $loyaltyService = app(\App\Services\LoyaltyService::class);
            $activityLogService = app(\App\Services\ActivityLogService::class);
            $notificationService = app(\App\Services\NotificationService::class);

            foreach ($orders as $order) {
                try {
                if ($order->payment_status === 'paid') {
                    $walletService->processOrderPayment($order);
                    $invoiceService->generateOrderInvoice($order, $user);
                    $loyaltyService->awardPointsForOrder($order);

                    $order->load(['couponEntitlements.coupon']);
                    $couponPayload = $order->couponEntitlements->map(function ($e) {
                        return [
                            'redeem_token' => $e->redeem_token,
                            'usage_limit' => (int) $e->usage_limit,
                            'remaining_uses' => $e->remainingUses(),
                            'coupon_title' => $e->coupon->title ?? $e->coupon->title_ar ?? '',
                        ];
                    })->values()->all();

                    \App\Jobs\SendOrderConfirmationEmail::dispatch($order, $couponPayload, $user->language ?? 'ar');
                }

                // Always create an in-app notification so users can see checkout result
                // even when email queue/push delivery is delayed.
                $notificationTitleAr = $order->payment_status === 'paid'
                    ? 'تم تأكيد طلبك'
                    : 'تم استلام طلبك';
                $notificationTitleEn = $order->payment_status === 'paid'
                    ? 'Your order is confirmed'
                    : 'Your order has been received';
                $notificationMessageAr = "تم إنشاء الطلب رقم #{$order->id} بقيمة {$order->total_amount}";
                $notificationMessageEn = "Order #{$order->id} created successfully. Total: {$order->total_amount}";

                $notificationPayload = [
                    'type' => 'order',
                    'order_id' => (int) $order->id,
                    'payment_status' => (string) $order->payment_status,
                    'title_ar' => $notificationTitleAr,
                    'title_en' => $notificationTitleEn,
                    'title' => $notificationTitleEn,
                    'message_ar' => $notificationMessageAr,
                    'message_en' => $notificationMessageEn,
                    'message' => $notificationMessageEn,
                ];

                $notificationService->sendNotification($user, 'order', $notificationPayload);

                if (($user->push_notifications ?? true) === true) {
                    $notificationService->sendFcmNotification(
                        $user,
                        $notificationTitleEn,
                        $notificationMessageEn,
                        [
                            'type' => 'order',
                            'order_id' => (string) $order->id,
                            'payment_status' => (string) $order->payment_status,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'route' => '/orders/' . $order->id,
                        ]
                    );
                }

                $activityLogService->logCreate(
                    $user->id,
                    Order::class,
                    $order->id,
                    "Order #{$order->id} created with total amount {$order->total_amount} EGP"
                );
                } catch (\Throwable $sideEffectException) {
                    Log::warning('Order post-commit side-effect failed', [
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'error' => $sideEffectException->getMessage(),
                    ]);
                }
            }

            // $orders is a Support Collection of Order models — call load() per model, not on the collection.
            foreach ($orders as $orderModel) {
                $orderModel->load(['items', 'coupons', 'couponEntitlements.coupon']);
            }

            if ($orders->count() === 1) {
                return response()->json([
                    'message' => 'Order created successfully',
                    'data' => [
                        'order' => new OrderResource($orders->first()),
                    ],
                ], 201);
            }

            return response()->json([
                'message' => 'Orders created successfully',
                'data' => [
                    'order' => new OrderResource($orders->first()),
                    'orders' => OrderResource::collection($orders),
                    'orders_count' => $orders->count(),
                    'total_amount' => (float) $orders->sum('total_amount'),
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
     * Checkout by coupon IDs — mobile "buy now" flow.
     *
     * POST body:
     *   - user_id (int, required, must match authenticated user)
     *   - coupon_ids (int[], required, at least one)
     *   - payment_method (string, optional, default 'cash')
     *   - notes (string, optional)
     *
     * Creates an Order with one OrderItem per coupon template (quantity = 1 each),
     * generates a CouponEntitlement per line and returns a QR code for the order
     * plus a shareable deep link so the user can redeem at the merchant.
     *
     * Note: this method is additive and does not modify the behaviour of the
     * legacy /orders/checkout cart-based flow which continues to work as before.
     */
    public function checkoutCoupons(Request $request, QrCodeService $qrService): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'coupon_ids' => 'required|array|min:1',
            'coupon_ids.*' => 'integer|exists:coupons,id',
            'payment_method' => 'sometimes|in:cash,card,none',
            'notes' => 'sometimes|nullable|string',
        ]);

        $user = $request->user();
        if ((int) $validated['user_id'] !== (int) $user->id) {
            return response()->json([
                'message' => 'user_id does not match the authenticated user',
                'message_ar' => 'معرف المستخدم لا يطابق المستخدم الحالي',
            ], 403);
        }

        $paymentMethod = $validated['payment_method'] ?? 'cash';
        if ($paymentMethod === 'card' && ! \App\Services\FeatureFlagService::isElectronicPaymentsEnabled()) {
            return response()->json([
                'message' => 'Electronic payments are currently disabled',
            ], 403);
        }

        $coupons = Coupon::with('offer.merchant')
            ->whereIn('id', $validated['coupon_ids'])
            ->get();

        if ($coupons->isEmpty()) {
            return response()->json([
                'message' => 'No valid coupons found',
                'message_ar' => 'لا توجد كوبونات صالحة',
            ], 422);
        }

        foreach ($coupons as $coupon) {
            if ($coupon->isExpired()) {
                return response()->json([
                    'message' => "Coupon #{$coupon->id} is expired",
                    'message_ar' => "الكوبون رقم {$coupon->id} منتهي الصلاحية",
                ], 422);
            }
            if (! $coupon->offer) {
                return response()->json([
                    'message' => "Coupon #{$coupon->id} is not linked to an offer",
                ], 422);
            }
        }

        $merchantIds = $coupons->pluck('offer.merchant_id')->unique()->values();
        if ($merchantIds->count() > 1) {
            return response()->json([
                'message' => 'All coupons must belong to the same merchant',
                'message_ar' => 'يجب أن تكون جميع الكوبونات لنفس التاجر',
            ], 422);
        }

        $merchantId = (int) $merchantIds->first();
        $totalAmount = (float) $coupons->sum(function (Coupon $c) {
            return (float) ($c->price_after_discount ?? $c->price ?? 0);
        });

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'merchant_id' => $merchantId,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'cash' ? 'pending' : 'paid',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($coupons as $coupon) {
                $unitPrice = (float) ($coupon->price_after_discount ?? $coupon->price ?? 0);

                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'offer_id' => $coupon->offer_id,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice,
                ]);

                $entitlementStatus = $order->payment_status === 'paid' ? 'active' : 'pending';
                CouponEntitlement::create([
                    'user_id' => $user->id,
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'usage_limit' => 1,
                    'times_used' => 0,
                    'reserved_shares_count' => 0,
                    'status' => $entitlementStatus,
                    'redeem_token' => $this->couponService->generateWalletRedeemToken(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Order creation failed: ' . $e->getMessage(),
            ], 500);
        }

        // Empty the user's cart after a successful checkout (same as cart-based checkout).
        // Best-effort — a failure here must not roll back the already-committed order.
        try {
            $userCart = \App\Models\Cart::where('user_id', $user->id)->first();
            if ($userCart) {
                $userCart->items()->delete();
            }
        } catch (\Throwable $cartEx) {
            \Illuminate\Support\Facades\Log::warning('checkoutCoupons: failed to clear cart after order commit', [
                'order_id' => $order->id,
                'error'    => $cartEx->getMessage(),
            ]);
        }

        $order->load(['items.offer', 'couponEntitlements.coupon.offer', 'merchant']);

        $scheme = (string) \App\Models\Setting::getValue('app_deep_link_scheme', 'ofroo');
        $orderToken = (string) (
            $order->couponEntitlements->first()->redeem_token ?? ''
        );
        $deepLink = $scheme . '://orders/' . $order->id
            . ($orderToken !== '' ? ('?token=' . $orderToken) : '');
        $webLanding = (string) \App\Models\Setting::getValue(
            'app_landing_url',
            rtrim((string) config('app.url', ''), '/')
        );
        $shareableLink = $webLanding !== ''
            ? rtrim($webLanding, '/') . '/orders/' . $order->id
            : $deepLink;

        $qrPayload = json_encode([
            'type' => 'order',
            'order_id' => $order->id,
            'user_id' => $user->id,
            'token' => $orderToken,
        ], JSON_UNESCAPED_UNICODE);
        $qrPayload = $qrPayload !== false ? $qrPayload : ('ORDER:' . $order->id);

        $qrDataUri = $qrService->dataUri($qrPayload);

        $couponsPayload = $order->couponEntitlements->map(function (CouponEntitlement $e) use ($qrService) {
            $perCouponPayload = json_encode([
                'type' => 'coupon_entitlement',
                'entitlement_id' => $e->id,
                'token' => $e->redeem_token,
            ], JSON_UNESCAPED_UNICODE) ?: ('ENT:' . $e->id);

            return [
                'entitlement_id' => $e->id,
                'coupon' => $e->coupon ? (new CouponResource($e->coupon))->toArray(request()) : null,
                'redeem_token' => $e->redeem_token,
                'qr_code_base64' => $qrService->dataUri($perCouponPayload, 260),
                'usage_limit' => (int) $e->usage_limit,
                'remaining_uses' => $e->remainingUses(),
                'status' => $e->status,
            ];
        })->values()->all();

        return response()->json([
            'message' => 'Order created successfully',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'merchant_id' => $order->merchant_id,
                    'total_amount' => (float) $order->total_amount,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at?->toIso8601String(),
                ],
                'coupons' => $couponsPayload,
                'payment' => [
                    'method' => $order->payment_method,
                    'status' => $order->payment_status,
                    'amount' => (float) $order->total_amount,
                    'currency' => (string) \App\Models\Setting::getValue('currency', 'SAR'),
                ],
                'qr_code' => [
                    'payload' => $qrPayload,
                    'base64' => $qrDataUri,
                    'format' => 'png',
                ],
                'shareable_link' => $shareableLink,
                'deep_link' => $deepLink,
            ],
        ], 201);
    }

    /**
     * Get user wallet coupons
     */
    public function walletCoupons(Request $request): JsonResponse
    {
        $user = $request->user();
        $entitlements = CouponEntitlement::with(['coupon.offer.merchant'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'exhausted'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CouponEntitlementResource::collection($entitlements->items()),
            'meta' => [
                'current_page' => $entitlements->currentPage(),
                'last_page' => $entitlements->lastPage(),
                'per_page' => $entitlements->perPage(),
                'total' => $entitlements->total(),
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
            // Cancel legacy coupon rows and wallet entitlements
            $order->coupons()->update([
                'status' => 'cancelled',
            ]);

            CouponEntitlement::where('order_id', $order->id)->update(['status' => 'cancelled']);

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

        $entitlements = CouponEntitlement::where('order_id', $order->id)
            ->with(['coupon.offer.merchant'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => CouponEntitlementResource::collection($entitlements),
        ]);
    }
}
