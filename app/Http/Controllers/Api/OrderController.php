<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponEntitlementResource;
use App\Http\Resources\CouponResource;
use App\Http\Resources\OrderResource;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Cart;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Setting;
use App\Services\ActivityLogService;
use App\Services\CouponService;
use App\Services\FeatureFlagService;
use App\Services\InvoiceGenerationService;
use App\Services\LoyaltyService;
use App\Services\NotificationService;
use App\Services\PaymentGatewayService;
use App\Services\QrCodeService;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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
            'cart_id' => [
                'nullable',
                Rule::exists('carts', 'id')->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                }),
            ],
        ]);

        // Check if electronic payments are enabled for card payments
        if ($request->payment_method === 'card' && ! FeatureFlagService::isElectronicPaymentsEnabled()) {
            return response()->json([
                'message' => 'Electronic payments are currently disabled',
            ], 403);
        }

        $user = $request->user();

        /**
         * Lock the cart row for update inside the transaction so:
         * - we read the latest cart_items (no stale empty read while another checkout clears the cart)
         * - concurrent checkouts for the same user serialize instead of double-charging / odd errors
         */
        DB::beginTransaction();
        try {
            $cartQuery = Cart::query()
                ->where('user_id', $user->id)
                ->lockForUpdate();

            if ($request->filled('cart_id')) {
                $cartQuery->where('id', (int) $request->input('cart_id'));
            }

            $cart = $cartQuery->first();

            if (! $cart) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Cart not found for this account.',
                    'message_ar' => 'لم يتم العثور على سلة لهذا الحساب. تأكد من cart_id أو سجّل الدخول بنفس المستخدم الذي أضاف للسلة.',
                    'message_en' => 'No cart found for this account. Check cart_id or use the same user who added items to the cart.',
                    'data' => [
                        'cart_id_requested' => $request->filled('cart_id') ? (int) $request->input('cart_id') : null,
                    ],
                ], 404);
            }

            $cart->load(['items.offer', 'items.coupon']);

            if ($cart->items->isEmpty()) {
                DB::rollBack();

                $recentOrders = Order::query()
                    ->where('user_id', $user->id)
                    ->where('created_at', '>=', now()->subMinutes(15))
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(['id', 'created_at', 'total_amount', 'payment_status']);

                return response()->json([
                    'message' => 'Cart is empty',
                    'message_ar' => 'سلة التسوق فارغة. إذا أكملتَ الدفع للتو، قد يكون الطلب قد أُنشئ بالفعل — راجع recent_orders. وإلا أضف عناصر عبر POST /api/cart/add بنفس التوكن.',
                    'message_en' => 'Your cart is empty. If you just paid, the order may already exist—see recent_orders. Otherwise add items with POST /api/cart/add using the same Bearer token.',
                    'data' => [
                        'cart_id' => (int) $cart->id,
                        'items_count' => 0,
                        'items_count_db' => (int) $cart->items()->count(),
                        'hint' => 'Checkout deletes cart lines after a successful order. Repeating checkout returns empty until you add items again.',
                        'recent_orders' => $recentOrders->map(fn (Order $o) => [
                            'id' => $o->id,
                            'created_at' => $o->created_at?->toIso8601String(),
                            'total_amount' => (float) $o->total_amount,
                            'payment_status' => (string) ($o->payment_status ?? ''),
                        ])->values()->all(),
                    ],
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
                DB::rollBack();

                return response()->json([
                    'message' => 'One or more cart items are not linked to a merchant',
                ], 422);
            }

            // Current payment flow supports one online payment intent; mixed-merchant carts must checkout as cash.
            if ($paymentMethod !== 'cash' && $groupedByMerchant->count() > 1) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Online checkout for multiple merchants is not supported. Please use cash or split the cart.',
                ], 422);
            }

            $payment = null;
            if ($paymentMethod !== 'cash') {
                $paymentGatewayService = app(PaymentGatewayService::class);
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

            // Reservation lifecycle: orders are created in `pending` state and held
            // for $reservationTtl minutes. The reservation is finalized at QR scan
            // (see QrActivationService) or released by the orders:expire-reservations
            // scheduled command. Inventory contract:
            //   - checkout:   coupons_remaining -= qty, reserved_quantity += qty
            //   - QR scan:    reserved_quantity -= qty, used_quantity += qty (coupons_remaining UNCHANGED)
            //   - expiry:     coupons_remaining += qty, reserved_quantity -= qty
            $reservationTtl = (int) Setting::getValue('reservation_ttl_minutes', 1440);
            $reservationExpiresAt = now()->addMinutes($reservationTtl);

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
                    'status' => 'pending',
                    'reservation_expires_at' => $reservationExpiresAt,
                ]);

                // Link payment when a single online payment is used.
                if (isset($payment)) {
                    app(PaymentGatewayService::class)->linkPaymentToOrder($payment, $order);
                }

                foreach ($merchantItems as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'offer_id' => $cartItem->offer_id,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $cartItem->price_at_add,
                        'total_price' => $cartItem->price_at_add * $cartItem->quantity,
                    ]);

                    // Reserve inventory only — do NOT consume.
                    // The atomic reserve() helper handles both coupons_remaining and
                    // reserved_quantity in a single SQL statement.
                    // coupons.times_used is intentionally NOT incremented here anymore;
                    // QR activation is the source of truth for redemption counters.
                    $cartItem->offer->reserve((int) $cartItem->quantity);
                }

                $this->couponService->createCouponsForOrder($order);
                if ($order->payment_status === 'paid') {
                    // Card / online prepaid path keeps today's behavior: entitlements
                    // become active immediately so the customer can use the wallet
                    // straight away. Cash entitlements stay 'pending' until QR scan.
                    $this->couponService->updateCouponStatusAfterPayment($order);
                }

                $orders->push($order);
            }

            // Empty the cart only after all merchant orders are created.
            $cart->items()->delete();

            DB::commit();

            $walletService = app(WalletService::class);
            $invoiceService = app(InvoiceGenerationService::class);
            $loyaltyService = app(LoyaltyService::class);
            $activityLogService = app(ActivityLogService::class);
            $notificationService = app(NotificationService::class);

            foreach ($orders as $order) {
                try {
                    if ($order->payment_status === 'paid') {
                        // Idempotency + row lock: mirror QrActivationService so parallel
                        // HTTP retries cannot double-credit under race conditions.
                        DB::transaction(function () use ($order, $walletService) {
                            $o = Order::whereKey($order->id)->lockForUpdate()->first();
                            if (! $o || $o->payment_status !== 'paid') {
                                return;
                            }
                            if ($o->wallet_processed_at !== null
                                || Commission::where('order_id', $o->id)->exists()) {
                                return;
                            }
                            $walletService->processOrderPayment($o);
                            $o->forceFill(['wallet_processed_at' => now()])->save();
                        });
                        $order->refresh();
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

                        SendOrderConfirmationEmail::dispatch($order, $couponPayload, $user->language ?? 'ar');
                    }
                } catch (\Throwable $paidSideEffectException) {
                    Log::warning('Order post-commit paid side-effects failed', [
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'error' => $paidSideEffectException->getMessage(),
                    ]);
                }

                // In-app + push + activity log must run even if wallet/invoice/loyalty failed above.
                try {
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
                                'route' => '/orders/'.$order->id,
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
                    Log::warning('Order post-commit notification/activity failed', [
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
                'message' => 'Order creation failed: '.$e->getMessage(),
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
        if ($paymentMethod === 'card' && ! FeatureFlagService::isElectronicPaymentsEnabled()) {
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

        // Reservation lifecycle: see OrderController::checkout for the inventory contract.
        // This endpoint never processed wallet/commission at checkout (the original
        // financial gap), so nothing to remove — the QR scan flow handles wallet
        // credit + commission idempotently when the order is first activated.
        $reservationTtl = (int) Setting::getValue('reservation_ttl_minutes', 1440);
        $reservationExpiresAt = now()->addMinutes($reservationTtl);

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'merchant_id' => $merchantId,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'cash' ? 'pending' : 'paid',
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'reservation_expires_at' => $reservationExpiresAt,
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

                // Reserve one unit per coupon line. Closes the inventory gap this
                // endpoint had previously: cart-checkout reserved, but coupons-checkout
                // did not, so coupons_remaining could be inconsistent across the two flows.
                if ($coupon->offer) {
                    $coupon->offer->reserve(1);
                }

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
                'message' => 'Order creation failed: '.$e->getMessage(),
            ], 500);
        }

        // Empty the user's cart after a successful checkout (same as cart-based checkout).
        // Best-effort — a failure here must not roll back the already-committed order.
        try {
            $userCart = Cart::where('user_id', $user->id)->first();
            if ($userCart) {
                $userCart->items()->delete();
            }
        } catch (\Throwable $cartEx) {
            Log::warning('checkoutCoupons: failed to clear cart after order commit', [
                'order_id' => $order->id,
                'error' => $cartEx->getMessage(),
            ]);
        }

        try {
            $notificationService = app(NotificationService::class);
            $notificationTitleAr = $order->payment_status === 'paid'
                ? 'تم تأكيد طلبك'
                : 'تم استلام طلبك';
            $notificationTitleEn = $order->payment_status === 'paid'
                ? 'Your order is confirmed'
                : 'Your order has been received';
            $notificationMessageAr = "تم إنشاء الطلب رقم #{$order->id} بقيمة {$order->total_amount}";
            $notificationMessageEn = "Order #{$order->id} created successfully. Total: {$order->total_amount}";

            $notificationService->sendNotification($user, 'order', [
                'type' => 'order',
                'order_id' => (int) $order->id,
                'payment_status' => (string) $order->payment_status,
                'title_ar' => $notificationTitleAr,
                'title_en' => $notificationTitleEn,
                'title' => $notificationTitleEn,
                'message_ar' => $notificationMessageAr,
                'message_en' => $notificationMessageEn,
                'message' => $notificationMessageEn,
            ]);

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
                        'route' => '/orders/'.$order->id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('checkoutCoupons: notification failed', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $order->load(['items.offer', 'couponEntitlements.coupon.offer', 'merchant']);

        $scheme = (string) Setting::getValue('app_deep_link_scheme', 'ofroo');
        // تنبيه: هذا الـQR يحمل توكن أول entitlement فقط.
        // عند تعدد الكوبونات في الطلب، استخدم coupons[].qr_code_base64
        // لكل بند على حدة لضمان تفعيل جميع البنود.
        $orderToken = (string) (
            $order->couponEntitlements->first()->redeem_token ?? ''
        );
        $deepLink = $scheme.'://orders/'.$order->id
            .($orderToken !== '' ? ('?token='.$orderToken) : '');
        $webLanding = (string) Setting::getValue(
            'app_landing_url',
            rtrim((string) config('app.url', ''), '/')
        );
        $shareableLink = $webLanding !== ''
            ? rtrim($webLanding, '/').'/orders/'.$order->id
            : $deepLink;

        $qrPayload = $orderToken;

        $qrDataUri = $qrService->dataUri($qrPayload);

        $couponsPayload = $order->couponEntitlements->map(function (CouponEntitlement $e) use ($qrService) {
            $perCouponPayload = (string) ($e->redeem_token ?? '');

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
                    'currency' => (string) Setting::getValue('currency', 'SAR'),
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
     * Get user wallet coupons (CouponEntitlement rows for the authenticated user).
     *
     * GET /api/wallet/coupons
     * GET /api/mobile/wallet/coupons
     *
     * Query (all optional):
     * - per_page (default 15)
     * - status: comma-separated filters. Literal entitlement statuses: active, pending, exhausted, cancelled, expired.
     *   Special: `expired` also matches time-based expiry (coupon.expires_at past, coupon.status=expired, offer ended).
     *   Default when omitted: active, pending, exhausted (hides cancelled; use status=cancelled to list those).
     * - merchant_id: filter by offer.merchant_id
     * - offer_id: filter by coupon.offer_id
     * - category_id: filter by offer.category_id
     * - mall_id: filter by offer.mall_id
     * - search: matches coupon title / title_ar / title_en / coupon_code / barcode (partial)
     */
    public function walletCoupons(Request $request): JsonResponse
    {
        $user = $request->user();

        $defaultStatuses = ['active', 'pending', 'exhausted'];
        $literalStatuses = ['active', 'pending', 'exhausted', 'cancelled', 'expired'];

        $query = CouponEntitlement::with(['coupon.offer.merchant'])
            ->where('user_id', $user->id);

        if ($request->filled('status')) {
            $requested = collect(preg_split('/\s*,\s*/', (string) $request->query('status'), -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn (string $s) => strtolower(trim($s)))
                ->unique()
                ->values();
            $known = $requested->intersect($literalStatuses)->values();

            if ($known->isEmpty()) {
                $query->whereIn('status', $defaultStatuses);
            } else {
                $wantsExpiredSemantics = $known->contains('expired');
                $literalOnly = $known->reject(fn (string $s) => $s === 'expired')->values()->all();

                if ($wantsExpiredSemantics && $literalOnly === []) {
                    $this->applyWalletExpiredFilter($query);
                } elseif ($wantsExpiredSemantics) {
                    $query->where(function (Builder $w) use ($literalOnly) {
                        $w->whereIn('status', $literalOnly)
                            ->orWhere(function (Builder $inner) {
                                $this->applyWalletExpiredFilter($inner);
                            });
                    });
                } else {
                    $query->whereIn('status', $literalOnly);
                }
            }
        } else {
            $query->whereIn('status', $defaultStatuses);
        }

        if ($request->filled('merchant_id')) {
            $mid = (int) $request->query('merchant_id');
            $query->whereHas('coupon.offer', fn ($q) => $q->where('merchant_id', $mid));
        }

        if ($request->filled('offer_id')) {
            $oid = (int) $request->query('offer_id');
            $query->whereHas('coupon', fn ($q) => $q->where('offer_id', $oid));
        }

        if ($request->filled('category_id')) {
            $cid = (int) $request->query('category_id');
            $query->whereHas('coupon.offer', fn ($q) => $q->where('category_id', $cid));
        }

        if ($request->filled('mall_id')) {
            $mallId = (int) $request->query('mall_id');
            $query->whereHas('coupon.offer', fn ($q) => $q->where('mall_id', $mallId));
        }

        if ($request->filled('search')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->query('search'))).'%';
            $query->whereHas('coupon', function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('title_ar', 'like', $term)
                        ->orWhere('title_en', 'like', $term)
                        ->orWhere('coupon_code', 'like', $term)
                        ->orWhere('barcode', 'like', $term);
                });
            });
        }

        $entitlements = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

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
     * Single wallet entitlement (coupon instance) for the authenticated user.
     * Includes paid line quantity when linked to an order item, plus order summary.
     *
     * GET /api/wallet/coupons/{id}
     * GET /api/mobile/wallet/coupons/{id}
     */
    public function walletCouponShow(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $entitlement = CouponEntitlement::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->with([
                'coupon.offer.merchant',
                'order',
                'orderItem',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => new CouponEntitlementResource($entitlement),
        ]);
    }

    /**
     * Wallet filter `status=expired`: entitlement row expired/cancelled, or linked coupon/offer ended by time or status.
     */
    private function applyWalletExpiredFilter(Builder $query): void
    {
        $query->where(function (Builder $w) {
            $w->whereIn('status', ['expired', 'cancelled'])
                ->orWhereHas('coupon', function (Builder $c) {
                    $c->where('status', 'expired')
                        ->orWhere(function (Builder $c2) {
                            $c2->whereNotNull('expires_at')
                                ->where('expires_at', '<', now());
                        });
                })
                ->orWhereHas('coupon.offer', function (Builder $o) {
                    $o->where('status', 'expired')
                        ->orWhere(function (Builder $o2) {
                            $o2->whereNotNull('end_date')
                                ->where('end_date', '<', now());
                        });
                });
        });
    }

    /**
     * POST /api/mobile/reviews — تقييم بعد الطلب (تاجر فقط أو تاجر + عرض).
     * Body:
     * - merchant_id, rating, notes?
     * - order_id (مطلوب لتقييم التاجر فقط)
     * - offer_id? (اختياري — إن وُجد يُربط بالعرض ويظهر على GET …/offers/{id})
     *
     * ملاحظة: عند تقييم عرض (offer_id موجود) يمكن إرسال order_id أو تركه فارغاً
     * وسيتم اختيار آخر طلب مؤهل للمستخدم يحتوي هذا العرض.
     */
    public function createReview(Request $request): JsonResponse
    {
        $request->validate([
            // When reviewing an offer (offer_id present), merchant_id can be omitted and will be inferred.
            'merchant_id' => 'nullable|exists:merchants,id',
            'offer_id' => 'nullable|integer|exists:offers,id',
            'rating' => 'required|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'notes_ar' => 'nullable|string',
            'notes_en' => 'nullable|string',
        ]);

        $user = $request->user();

        $offerId = $request->filled('offer_id') ? (int) $request->input('offer_id') : null;
        $merchantId = $request->filled('merchant_id') ? (int) $request->input('merchant_id') : null;

        if ($offerId === null && $merchantId === null) {
            return response()->json([
                'message' => 'merchant_id is required when reviewing a merchant.',
                'message_ar' => 'معرّف التاجر مطلوب عند تقييم التاجر.',
            ], 422);
        }

        if ($offerId === null && ! $request->filled('order_id')) {
            return response()->json([
                'message' => 'order_id is required when reviewing a merchant.',
                'message_ar' => 'رقم الطلب مطلوب عند تقييم التاجر.',
            ], 422);
        }

        $order = null;
        if ($request->filled('order_id')) {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereKey($request->order_id)
                ->with('items')
                ->firstOrFail();
        }

        if ($offerId !== null) {
            if (! Schema::hasColumn('reviews', 'offer_id')) {
                return response()->json([
                    'message' => 'Offer reviews are not available until database migration is applied.',
                    'message_ar' => 'تقييم العروض غير مفعّل بعد — شغّل migrations.',
                ], 503);
            }

            $offer = Offer::query()->whereKey($offerId)->firstOrFail();

            // Infer merchant_id from the offer when not provided.
            if ($merchantId === null) {
                $merchantId = (int) $offer->merchant_id;
            }

            if ((int) $offer->merchant_id !== (int) $merchantId) {
                return response()->json([
                    'message' => 'merchant_id does not match the offer owner.',
                    'message_ar' => 'معرّف التاجر لا يطابق مالك العرض.',
                ], 422);
            }

            // If order_id not provided, auto-pick the latest eligible order that includes this offer.
            if ($order === null) {
                $order = Order::query()
                    ->where('user_id', $user->id)
                    ->where('merchant_id', (int) $merchantId)
                    ->where(function ($q) {
                        $q->where('payment_status', 'paid')
                            ->orWhere('status', 'activated');
                    })
                    ->whereHas('items', fn ($q) => $q->where('offer_id', $offerId))
                    ->with('items')
                    ->orderByDesc('id')
                    ->first();

                if (! $order) {
                    return response()->json([
                        'message' => 'No eligible order found to review this offer.',
                        'message_ar' => 'لا يوجد طلب مؤهل لتقييم هذا العرض.',
                    ], 422);
                }
            }

            $hasOfferLine = $order->items->contains(fn (OrderItem $item) => (int) $item->offer_id === $offerId);
            if (! $hasOfferLine) {
                return response()->json([
                    'message' => 'This order does not include the specified offer.',
                    'message_ar' => 'الطلب لا يحتوي على هذا العرض.',
                ], 422);
            }

            $duplicate = Review::query()
                ->where('user_id', $user->id)
                ->where('order_id', $order->id)
                ->where('offer_id', $offerId)
                ->exists();
            if ($duplicate) {
                return response()->json([
                    'message' => 'You have already reviewed this offer for this order.',
                    'message_ar' => 'لقد قيّمت هذا العرض لهذا الطلب مسبقاً.',
                ], 409);
            }
        }

        $visibleToPublic = $offerId !== null;

        $review = $user->reviews()->create([
            'merchant_id' => $merchantId,
            'order_id' => $order?->id,
            'offer_id' => $offerId,
            'rating' => $request->rating,
            'notes' => $request->notes,
            'notes_ar' => $request->notes_ar,
            'notes_en' => $request->notes_en,
            'visible_to_public' => $visibleToPublic,
        ]);

        return response()->json([
            'message' => 'Review created successfully',
            'data' => $review->fresh()->loadMissing(['offer:id,title,title_en', 'merchant:id,company_name_ar,company_name_en']),
        ], 201);
    }

    /**
     * POST /api/mobile/offers/{offer}/reviews — نفس منطق createReview مع offer_id من المسار.
     */
    public function createOfferReview(Request $request, string $offer): JsonResponse
    {
        if (! ctype_digit($offer)) {
            abort(404);
        }
        $request->merge(['offer_id' => (int) $offer]);

        return $this->createReview($request);
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
                'message' => 'Cannot cancel order. Payment status: '.$order->payment_status,
            ], 400);
        }

        DB::beginTransaction();
        try {
            $order = Order::with('items.offer')
                ->where('user_id', $user->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payment_status !== 'pending') {
                DB::rollBack();

                return response()->json([
                    'message' => 'Cannot cancel order. Payment status: '.$order->payment_status,
                ], 400);
            }

            // Cancel legacy coupon rows and wallet entitlements
            $order->coupons()->update([
                'status' => 'cancelled',
            ]);

            CouponEntitlement::where('order_id', $order->id)->update(['status' => 'cancelled']);

            // Symmetric to reserve() at checkout — only while the reservation
            // is still active. If status is already `expired`, the scheduled job
            // already ran releaseReservation(); releasing again would corrupt counts.
            if ((string) ($order->status ?? '') === 'pending') {
                foreach ($order->items as $item) {
                    if ($item->offer) {
                        $item->offer->releaseReservation((int) $item->quantity);
                    }
                }
            }

            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order cancelled successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Order cancellation failed: '.$e->getMessage(),
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
