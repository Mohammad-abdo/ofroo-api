<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    /**
     * Get payment gateways
     */
    public function paymentGateways(): JsonResponse
    {
        $gateways = PaymentGateway::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'data' => $gateways,
        ]);
    }

    /**
     * Create payment gateway
     */
    public function createPaymentGateway(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:payment_gateways,name',
            'display_name' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $gateway = PaymentGateway::create($request->all());

        return response()->json([
            'message' => 'Payment gateway created successfully',
            'data' => $gateway,
        ], 201);
    }

    /**
     * Update payment gateway
     */
    public function updatePaymentGateway(Request $request, string $id): JsonResponse
    {
        $gateway = PaymentGateway::findOrFail($id);
        $gateway->update($request->all());

        return response()->json([
            'message' => 'Payment gateway updated successfully',
            'data' => $gateway,
        ]);
    }

    /**
     * Get single payment gateway (Admin)
     */
    public function getPaymentGateway(string $id): JsonResponse
    {
        $gateway = PaymentGateway::findOrFail($id);

        return response()->json([
            'data' => $gateway,
        ]);
    }

    /**
     * Delete payment gateway (Admin)
     */
    public function deletePaymentGateway(string $id): JsonResponse
    {
        $gateway = PaymentGateway::findOrFail($id);
        $gateway->delete();

        return response()->json([
            'message' => 'Payment gateway deleted successfully',
        ]);
    }
}
