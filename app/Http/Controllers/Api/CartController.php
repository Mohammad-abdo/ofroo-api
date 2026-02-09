<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get user cart
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load(['items.offer.merchant', 'items.offer.category']);

        $total = $cart->items->sum(function ($item) {
            return $item->price_at_add * $item->quantity;
        });

        return response()->json([
            'data' => [
                'id' => $cart->id,
                'items' => $cart->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'offer' => [
                            'id' => $item->offer->id,
                            'title_ar' => $item->offer->title_ar,
                            'title_en' => $item->offer->title_en,
                            'price' => (float) $item->offer->price,
                            'images' => $item->offer->images ?? [],
                        ],
                        'quantity' => $item->quantity,
                        'price_at_add' => (float) $item->price_at_add,
                        'subtotal' => (float) ($item->price_at_add * $item->quantity),
                    ];
                }),
                'total' => $total,
            ],
        ]);
    }

    /**
     * Add item to cart
     */
    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'offer_id' => 'required|exists:offers,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $offer = Offer::findOrFail($request->offer_id);

        // Check if offer is available
        if ($offer->status !== 'active' || $offer->coupons_remaining < $request->quantity) {
            return response()->json([
                'message' => 'Offer not available or insufficient coupons',
            ], 400);
        }

        // Check if item already exists in cart
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('offer_id', $offer->id)
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
     * Update cart item quantity
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('id', $id)
            ->with('offer')
            ->firstOrFail();

        // Check if offer has enough coupons
        if ($cartItem->offer->coupons_remaining < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient coupons available',
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
     * Remove item from cart
     */
    public function remove(Request $request, string $id): JsonResponse
    {
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
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->firstOrFail();

        $cart->items()->delete();

        return response()->json([
            'message' => 'Cart cleared successfully',
        ]);
    }
}
