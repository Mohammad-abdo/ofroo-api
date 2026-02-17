<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Ensure request is authenticated. Routes must use auth:sanctum middleware; this is a safety net.
     */
    private function ensureAuthenticated(Request $request): ?JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. You must log in to use the cart.',
                'message_ar' => 'يجب تسجيل الدخول لاستخدام السلة. الرجاء تسجيل الدخول أو إنشاء حساب.',
                'message_en' => 'You must be logged in to use the cart.',
            ], 401);
        }
        return null;
    }

    /**
     * Get user cart (requires auth + token).
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }
        $user = $request->user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load(['items.offer.merchant', 'items.offer.category', 'items.offer.coupons', 'items.coupon']);

        $total = $cart->items->sum(function ($item) {
            if ($item->coupon_id && $item->relationLoaded('coupon') && $item->coupon) {
                return (float) $item->coupon->price_after_discount * $item->quantity;
            }
            return $item->price_at_add * $item->quantity;
        });

        return response()->json([
            'data' => [
                'id' => $cart->id,
                'items' => $cart->items->map(function ($item) {
                    $offer = $item->offer;
                    $hasSpecificCoupon = $item->coupon_id && $item->relationLoaded('coupon') && $item->coupon;

                    $offerPayload = [
                        'id' => $offer->id,
                        'title' => $offer->title ?? $offer->title_ar ?? $offer->title_en ?? '',
                        'description' => $offer->description ?? '',
                        'price' => (float) $offer->price,
                        'discount' => (float) ($offer->discount ?? 0),
                        'offer_images' => $offer->offer_images ?? [],
                        'status' => $offer->status ?? '',
                        'merchant' => $offer->relationLoaded('merchant') && $offer->merchant ? [
                            'id' => $offer->merchant->id,
                            'company_name' => $offer->merchant->company_name ?? '',
                        ] : null,
                        'category' => $offer->relationLoaded('category') && $offer->category ? [
                            'id' => $offer->category->id,
                            'name' => $offer->category->name ?? $offer->category->name_ar ?? $offer->category->name_en ?? '',
                        ] : null,
                    ];

                    if ($hasSpecificCoupon) {
                        $priceAfterDiscount = (float) $item->coupon->price_after_discount;
                        $qty = (int) $item->quantity;
                        return [
                            'id' => $item->id,
                            'offer_id' => (int) $item->offer_id,
                            'coupon' => (new CouponResource($item->coupon))->resolve(),
                            'quantity' => $qty,
                            'price_at_add' => (float) $item->price_at_add,
                            'price_after_discount' => $priceAfterDiscount,
                            'subtotal' => (float) $item->price_at_add,
                            'subtotal_after_discount' => round($priceAfterDiscount * $qty, 2),
                        ];
                    }

                    $allCoupons = $offer->relationLoaded('coupons') ? $offer->coupons : $offer->coupons()->get();
                    $availableCoupons = $allCoupons->filter(function ($c) {
                        if (($c->status ?? '') !== 'active') {
                            return false;
                        }
                        if ($c->expires_at && $c->expires_at->isPast()) {
                            return false;
                        }
                        $remaining = (int) ($c->usage_limit ?? 1) - (int) ($c->times_used ?? 0);
                        return $remaining > 0;
                    });

                    return [
                        'id' => $item->id,
                        'offer' => $offerPayload,
                        'coupons_count' => $item->quantity,
                        'available_coupons' => CouponResource::collection($availableCoupons->values())->resolve(),
                        'quantity' => $item->quantity,
                        'price_at_add' => (float) $item->price_at_add,
                        'subtotal' => (float) ($item->price_at_add * $item->quantity),
                    ];
                }),
                'total' => round($total, 2),
            ],
        ]);
    }

    /**
     * Add item to cart (requires auth + token).
     * Accepts either:
     * - coupon_id: add this specific coupon (one coupon per line).
     * - offer_id + quantity: add quantity coupons from this offer (allocated at checkout).
     */
    public function add(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }

        $user = $request->user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        if ($request->has('coupon_id') && $request->coupon_id) {
            return $this->addCouponToCart($request, $cart);
        }

        $request->validate([
            'offer_id' => 'required|exists:offers,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $offer = Offer::with('coupons')->findOrFail($request->offer_id);

        $available = $offer->available_coupons_count;
        if ($available <= 0 && \Schema::hasColumn('offers', 'coupons_remaining')) {
            $available = (int) $offer->coupons_remaining;
        }

        if ($offer->status !== 'active' || $available < $request->quantity) {
            return response()->json([
                'message' => 'Offer not available or coupon usage limit exhausted.',
                'message_ar' => 'العرض غير متاح أو تم استنفاد حد استخدام الكوبون',
                'message_en' => 'This offer is unavailable or the coupon usage limit has been exhausted.',
            ], 400);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('offer_id', $offer->id)
            ->whereNull('coupon_id')
            ->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->quantity,
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'offer_id' => $offer->id,
                'quantity' => $request->quantity,
                'price_at_add' => $offer->price,
            ]);
        }

        return response()->json([
            'message' => 'Item added to cart successfully',
        ]);
    }

    /**
     * Add a specific coupon to cart (user buys one coupon from the offer).
     * Body: coupon_id (required), offer_id (optional - validated if sent).
     */
    private function addCouponToCart(Request $request, Cart $cart): JsonResponse
    {
        $request->validate([
            'coupon_id' => 'required|exists:coupons,id',
            'offer_id' => 'nullable|exists:offers,id',
        ]);

        $coupon = Coupon::with('offer')->findOrFail($request->coupon_id);

        if ($request->filled('offer_id') && (int) $coupon->offer_id !== (int) $request->offer_id) {
            return response()->json([
                'message' => 'Coupon does not belong to this offer.',
                'message_ar' => 'هذا الكوبون لا ينتمي لهذا العرض.',
                'message_en' => 'This coupon does not belong to this offer.',
            ], 400);
        }

        if ($coupon->offer_id === null || ! $coupon->offer) {
            return response()->json([
                'message' => 'Coupon has no offer.',
                'message_ar' => 'هذا الكوبون غير مرتبط بعرض.',
                'message_en' => 'This coupon is not linked to an offer.',
            ], 400);
        }

        $offer = $coupon->offer;
        if ($offer->status !== 'active') {
            return response()->json([
                'message' => 'Offer is not active.',
                'message_ar' => 'العرض غير نشط.',
                'message_en' => 'Offer is not active.',
            ], 400);
        }

        if (($coupon->status ?? '') !== 'active') {
            return response()->json([
                'message' => 'Coupon is not available.',
                'message_ar' => 'الكوبون غير متاح أو تم استنفاده.',
                'message_en' => 'This coupon is not available or has been exhausted.',
            ], 400);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json([
                'message' => 'Coupon has expired.',
                'message_ar' => 'انتهت صلاحية الكوبون.',
                'message_en' => 'This coupon has expired.',
            ], 400);
        }

        $limit = (int) ($coupon->usage_limit ?? 1);
        $used = (int) ($coupon->times_used ?? 0);
        if ($used >= $limit) {
            return response()->json([
                'message' => 'Coupon usage limit exhausted.',
                'message_ar' => 'تم استنفاد حد استخدام هذا الكوبون.',
                'message_en' => 'This coupon has been exhausted.',
            ], 400);
        }

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('coupon_id', $coupon->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This coupon is already in your cart.',
                'message_ar' => 'هذا الكوبون موجود بالفعل في السلة.',
                'message_en' => 'This coupon is already in your cart.',
            ], 400);
        }

        CartItem::create([
            'cart_id' => $cart->id,
            'offer_id' => $offer->id,
            'coupon_id' => $coupon->id,
            'quantity' => 1,
            'price_at_add' => $coupon->price ?? $offer->price,
        ]);

        return response()->json([
            'message' => 'Coupon added to cart successfully',
            'message_ar' => 'تم إضافة الكوبون إلى السلة',
            'message_en' => 'Coupon added to cart successfully',
        ]);
    }

    /**
     * Update cart item quantity (requires auth + token).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $id)
            ->with('offer.coupons')
            ->firstOrFail();

        $available = $cartItem->offer->available_coupons_count;
        if ($available <= 0 && \Schema::hasColumn('offers', 'coupons_remaining')) {
            $available = (int) $cartItem->offer->coupons_remaining;
        }

        if ($available < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient coupons available',
                'message_ar' => 'تم استنفاد حد استخدام الكوبون أو الكمية غير متوفرة',
                'message_en' => 'The coupon usage limit has been exhausted or quantity not available.',
            ], 400);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
        ]);

        return response()->json([
            'message' => 'Cart item updated successfully',
            'data' => [
                'id' => $cartItem->id,
                'quantity' => $cartItem->quantity,
                'subtotal' => (float) ($cartItem->price_at_add * $cartItem->quantity),
            ],
        ]);
    }

    /**
     * Remove item from cart (requires auth + token).
     */
    public function remove(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }
        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart successfully',
        ]);
    }

    /**
     * Clear entire cart (requires auth + token).
     */
    public function clear(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated($request)) {
            return $response;
        }
        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared successfully',
        ]);
    }
}
