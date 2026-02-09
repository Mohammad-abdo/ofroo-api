<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Offer;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
{
    /**
     * List coupons for a specific offer.
     */
    public function index(Offer $offer): JsonResponse
    {
        return response()->json([
            'data' => CouponResource::collection($offer->coupons),
        ]);
    }

    /**
     * Store a coupon under an offer.
     */
    public function store(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('update', $offer);

        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'barcode' => 'nullable|string|unique:coupons,barcode',
            'expires_at' => 'nullable|date',
        ]);

        $coupon = $offer->coupons()->create($validated);

        return response()->json([
            'message' => 'Coupon created successfully',
            'data' => new CouponResource($coupon),
        ], 201);
    }

    /**
     * Update a specific coupon.
     */
    public function update(Request $request, Offer $offer, Coupon $coupon): JsonResponse
    {
        Gate::authorize('update', $offer);

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'status' => 'sometimes|in:active,used,expired',
        ]);

        $coupon->update($validated);

        return response()->json([
            'message' => 'Coupon updated successfully',
            'data' => new CouponResource($coupon),
        ]);
    }

    /**
     * Delete a coupon.
     */
    public function destroy(Offer $offer, Coupon $coupon): JsonResponse
    {
        Gate::authorize('update', $offer);

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully',
        ]);
    }
}
